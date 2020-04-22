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
use Kahlan\Plugin\Double;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\Micro\CommandSpecification;
use function Prooph\Micro\Kernel\stateResolver;

describe('Prooph Micro', function () {
    context('Kernel', function () {
        describe('State Resolver', function () {
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
                allow(StreamEventsSlice::class)->toReceive('status')->andReturn(SliceReadStatus::streamNotFound());

                $closure = fn () => wait(stateResolver($connection, $spec)());

                expect($closure)->toThrow(new \RuntimeException('Stream not found'));
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
                allow(StreamEventsSlice::class)->toReceive('status')->andReturn(SliceReadStatus::streamDeleted());

                $closure = fn () => wait(stateResolver($connection, $spec)());

                expect($closure)->toThrow(new \RuntimeException('Stream deleted'));
            });
        });
    });
});
