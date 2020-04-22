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

namespace Prooph\Micro\Kernel;

use function Amp\call;
use Amp\Promise;
use Closure;
use function Failure;
use Generator;
use Phunkie\Types\ImmMap;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\Micro\CommandSpecification;
use function Success;

const buildCommandDispatcher = 'Prooph\Micro\Kernel\buildCommandDispatcher';

function buildCommandDispatcher(
    EventStoreConnection $eventStore,
    ImmMap $commandMap
): callable {
    return function (object $m) use ($eventStore, $commandMap): Promise {
        return call(function () use ($m, $eventStore, $commandMap): Generator {
            $messageClass = \get_class($m);
            $config = $commandMap->get($messageClass);

            if ($config->isEmpty()) {
                return Failure('No configuration found for ' . $messageClass);
            }

            $specification = $config->get()($m);
            \assert($specification instanceof CommandSpecification);

            try {
                $es = yield $specification->handle(stateResolver($eventStore, $specification));

                yield $eventStore->appendToStreamAsync(
                    $specification->streamName(),
                    $specification->expectedVersion(),
                    $es->map(fn ($e) => $specification->mapToEventData($e))->toArray()
                );
            } catch (\Throwable $e) {
                return Failure($e);
            }

            return Success($es);
        });
    };
}

const stateResolver = 'Prooph\Micro\Kernel\stateResolver';

function stateResolver(EventStoreConnection $eventStore, CommandSpecification $specification): Closure
{
    return function () use ($eventStore, $specification): Promise {
        return call(function () use ($eventStore, $specification): Generator {
            $slice = yield $eventStore->readStreamEventsForwardAsync(
                $specification->streamName(),
                0,
                4096,
            );

            \assert($slice instanceof StreamEventsSlice);

            switch ($slice->status()->value()) {
                case SliceReadStatus::SUCCESS:
                    return $specification->reconstituteFromHistory(ImmList(...$slice->events()));
                    break;
                case SliceReadStatus::STREAM_NOT_FOUND:
                    throw new \RuntimeException('Stream not found');
                    break;
                case SliceReadStatus::STREAM_DELETED:
                    throw new \RuntimeException('Stream deleted');
                    break;
            }
        });
    };
}
