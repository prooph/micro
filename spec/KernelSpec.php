<?php

/**
 * This file is part of the prooph/micro.
 * (c) 2017-2020 prooph software GmbH <contact@prooph.de>
 * (c) 2017-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\Micro;

use function Amp\Promise\wait;
use Amp\Success;
use function expect;
use Kahlan\Plugin\Double;
use Phunkie\Types\ImmList;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\Micro\CommandSpecification;
use function Prooph\Micro\Kernel\buildCommandDispatcher;
use function Prooph\Micro\Kernel\stateResolver;
use Prooph\Micro\NotFound;
use RuntimeException;

describe('Prooph Micro', function () {
    context('Kernel', function () {
        describe('when resolving state', function () {
            it('will reconstitute state when event history found', function () {
                $command = new \stdClass();
                $handler = fn ($s, $m) => $s();

                $spec = Double::instance([
                    'extends' => CommandSpecification::class,
                    'args' => [$command, $handler],
                ]);

                $connection = Double::instance([
                    'implements' => EventStoreConnection::class,
                ]);

                $slice = Double::instance([
                    'extends' => StreamEventsSlice::class,
                    'magicMethods' => true,
                ]);

                $now = new \DateTimeImmutable();

                $re1 = new ResolvedEvent(new RecordedEvent('stream', 0, EventId::generate(), 'test', false, '1', '', $now), null, null);
                $re2 = new ResolvedEvent(new RecordedEvent('stream', 0, EventId::generate(), 'test', false, '2', '', $now), null, null);
                $re3 = new ResolvedEvent(new RecordedEvent('stream', 0, EventId::generate(), 'test', false, '3', '', $now), null, null);

                allow($spec)->toReceive('streamName')->andReturn('test-stream');
                allow($spec)->toReceive('initialState')->andReturn(0);
                allow($spec)->toReceive('mapToEvent')->andRun(function (ResolvedEvent $re): object {
                    $e = new \stdClass();
                    $e->v = (int) $re->originalEvent()->data();

                    return $e;
                });
                allow($spec)->toReceive('apply')->andReturn(6);

                allow($connection)->toReceive('readStreamEventsForwardAsync')->andReturn(new Success($slice));

                allow($slice)->toReceive('isEndOfStream')->andReturn(true);
                allow($slice)->toReceive('status')->andReturn(SliceReadStatus::success());
                allow($slice)->toReceive('events')->andReturn([$re1, $re2, $re3]);

                expect(wait(stateResolver($connection, $spec, 5)()))->toBe(6);
            });

            it('will throw, when stream not found', function () {
                $command = new \stdClass();
                $handler = fn ($s, $m) => $s();

                $spec = Double::instance([
                    'extends' => CommandSpecification::class,
                    'args' => [$command, $handler],
                ]);

                $connection = Double::instance([
                    'implements' => EventStoreConnection::class,
                ]);

                $slice = Double::instance([
                    'extends' => StreamEventsSlice::class,
                    'magicMethods' => true,
                ]);

                allow($spec)->toReceive('streamName')->andReturn('test-stream');
                allow($connection)->toReceive('readStreamEventsForwardAsync')->andReturn(new Success($slice));
                allow($slice)->toReceive('status')->andReturn(SliceReadStatus::streamNotFound());

                $closure = fn () => wait(stateResolver($connection, $spec, 5)());

                expect($closure)->toThrow(new NotFound('Stream not found'));
            });

            it('will throw, when stream deleted', function () {
                $command = new \stdClass();
                $handler = fn ($s, $m) => $s();

                $spec = Double::instance([
                    'extends' => CommandSpecification::class,
                    'args' => [$command, $handler],
                ]);

                $connection = Double::instance([
                    'implements' => EventStoreConnection::class,
                ]);

                $slice = Double::instance([
                    'extends' => StreamEventsSlice::class,
                    'magicMethods' => true,
                ]);

                allow($spec)->toReceive('streamName')->andReturn('test-stream');
                allow($connection)->toReceive('readStreamEventsForwardAsync')->andReturn(new Success($slice));
                allow($slice)->toReceive('status')->andReturn(SliceReadStatus::streamDeleted());

                $closure = fn () => wait(stateResolver($connection, $spec, 5)());

                expect($closure)->toThrow(new NotFound('Stream deleted'));
            });
        });

        describe('when executing the command dispatcher', function () {
            it('will execute the command handler successfully', function () {
                $command = new \stdClass();
                $event = new \stdClass();
                $event->v = 'foo';
                $handler = function ($s, $m) use ($event) {
                    yield $event;
                };
                $enrich = fn ($e) => $e;

                $spec = Double::instance([
                    'extends' => CommandSpecification::class,
                    'args' => [$command, $handler, $enrich],
                ]);

                $map = ImmMap([
                    \stdClass::class => fn ($m) => $spec,
                ]);

                $connection = Double::instance([
                    'implements' => EventStoreConnection::class,
                ]);

                allow($spec)->toReceive('streamName')->andReturn('test-stream');
                allow($spec)->toReceive('enrich')->andReturn($event);
                allow($spec)->toReceive('mapToEventData')->andRun(function (object $e): object {
                    return new EventData(
                        null,
                        'test-event',
                        false,
                        $e->v,
                        ''
                    );
                });

                allow($connection)->toReceive('appendToStreamAsync')->andRun(fn () => new Success());

                $dispatch = buildCommandDispatcher($connection, $map, fn ($e) => $e);

                $result = wait($dispatch($command));

                $expectedEvent = new \stdClass();
                $expectedEvent->v = 'foo';

                expect($result)->toBeAnInstanceOf(ImmList::class);
                expect($result->head())->toEqual($expectedEvent);
            });

            it('returns failure when no specification found for a given command', function () {
                $command = new \stdClass();
                $map = ImmMap();

                $connection = Double::instance([
                    'implements' => EventStoreConnection::class,
                ]);

                $dispatch = buildCommandDispatcher($connection, $map, fn ($e) => $e);
                $syncedDispatch = fn () => wait($dispatch($command));

                expect($syncedDispatch)->toThrow(
                    new RuntimeException('No configuration found for stdClass')
                );
            });

            it('returns failure when command handler throws', function () {
                $command = new \stdClass();
                $handler = function ($s, $m) {
                    throw new \RuntimeException('Boom!');
                };

                $spec = Double::instance([
                    'extends' => CommandSpecification::class,
                    'args' => [$command, $handler],
                ]);

                $map = ImmMap([
                    \stdClass::class => fn ($m) => $spec,
                ]);

                $connection = Double::instance([
                    'implements' => EventStoreConnection::class,
                ]);

                $slice = Double::instance([
                    'extends' => StreamEventsSlice::class,
                    'magicMethods' => true,
                ]);

                $now = new \DateTimeImmutable();

                $re1 = new ResolvedEvent(new RecordedEvent('stream', 0, EventId::generate(), 'test', false, '1', '', $now), null, null);
                $re2 = new ResolvedEvent(new RecordedEvent('stream', 0, EventId::generate(), 'test', false, '2', '', $now), null, null);
                $re3 = new ResolvedEvent(new RecordedEvent('stream', 0, EventId::generate(), 'test', false, '3', '', $now), null, null);

                allow($spec)->toReceive('streamName')->andReturn('test-stream');
                allow($spec)->toReceive('initialState')->andReturn(0);
                allow($spec)->toReceive('mapToEvent')->andRun(function (ResolvedEvent $re): object {
                    $e = new \stdClass();
                    $e->v = (int) $re->originalEvent()->data();

                    return $e;
                });
                allow($spec)->toReceive('apply')->andReturn(6);

                allow($connection)->toReceive('readStreamEventsForwardAsync')->andReturn(new Success($slice));

                allow($slice)->toReceive('status')->andReturn(SliceReadStatus::success());
                allow($slice)->toReceive('events')->andReturn([$re1, $re2, $re3]);

                $dispatch = buildCommandDispatcher($connection, $map, fn ($e) => $e);
                $syncedDispatch = fn () => wait($dispatch($command));

                expect($syncedDispatch)->toThrow(new RuntimeException('Boom!'));
            });
        });
    });
});
