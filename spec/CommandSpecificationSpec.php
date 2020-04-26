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

use Amp\Success;
use Kahlan\Plugin\Double;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\ResolvedEvent;
use Prooph\Micro\CommandSpecification;

describe('Prooph Micro', function () {
    context('Command Specification', function () {
        describe('Command Handling', function () {
            it('can handle incoming commands', function () {
                $command = new \stdClass();
                $handler = fn ($s, $m) => yield 'foo';

                $spec = Double::instance([
                    'extends' => CommandSpecification::class,
                    'args' => [$command, $handler],
                ]);

                expect($spec->handle(fn () => []))->toBeAnInstanceOf(\Generator::class);
            });
        });

        describe('Aggregate State Reconstruction', function () {
            it('can reconstitute aggregate state from event history', function () {
                $command = new \stdClass();
                $handler = fn () => new Success();

                $spec = Double::instance([
                    'extends' => CommandSpecification::class,
                    'args' => [$command, $handler],
                ]);
                allow($spec)->toReceive('initialState')->andReturn(0);
                allow($spec)->toReceive('mapToEvent')->andRun(function (ResolvedEvent $re): object {
                    $e = new \stdClass();
                    $e->v = (int) $re->originalEvent()->data();

                    return $e;
                });
                allow($spec)->toReceive('apply')->andReturn(6);

                $now = new \DateTimeImmutable();

                $re1 = new ResolvedEvent(new RecordedEvent('stream', 0, EventId::generate(), 'test', false, '1', '', $now), null, null);
                $re2 = new ResolvedEvent(new RecordedEvent('stream', 0, EventId::generate(), 'test', false, '2', '', $now), null, null);
                $re3 = new ResolvedEvent(new RecordedEvent('stream', 0, EventId::generate(), 'test', false, '3', '', $now), null, null);

                expect($spec->reconstituteFromHistory(ImmList($re1, $re2, $re3)))->toBe(6);
            });
        });

        describe('Default expected version value', function () {
            it('will return "any" as default value for expected version', function () {
                $command = new \stdClass();
                $handler = fn () => new Success();

                $spec = Double::instance([
                    'extends' => CommandSpecification::class,
                    'args' => [$command, $handler],
                ]);

                expect($spec->expectedVersion())->toBe(ExpectedVersion::ANY);
            });
        });
    });
});
