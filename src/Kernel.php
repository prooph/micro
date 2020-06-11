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
use Amp\Producer;
use Amp\Promise;
use Closure;
use Generator;
use Phunkie\Types\ImmMap;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\Micro\CommandSpecification;
use Prooph\Micro\NotFound;
use RuntimeException;

const buildCommandDispatcher = 'Prooph\Micro\Kernel\buildCommandDispatcher';

function buildCommandDispatcher(
    EventStoreConnection $eventStore,
    ImmMap $commandMap,
    int $readBatchSize = 200
): callable {
    return function (object $m) use ($eventStore, $commandMap, $readBatchSize): Promise {
        return call(function () use ($m, $eventStore, $commandMap, $readBatchSize): Generator {
            $messageClass = \get_class($m);
            $config = $commandMap->get($messageClass);

            if ($config->isEmpty()) {
                throw new RuntimeException(
                    'No configuration found for ' . $messageClass
                );
            }

            $specification = $config->get()($m);
            \assert($specification instanceof CommandSpecification);

            $iterator = new Producer(function (callable $emit) use ($eventStore, $specification, $readBatchSize): Generator {
                $generator = $specification->handle(stateResolver($eventStore, $specification, $readBatchSize));

                while ($generator->valid()) {
                    $eventOrPromise = $generator->current();

                    if ($eventOrPromise instanceof Promise) {
                        $generator->send(yield $eventOrPromise);
                    } else {
                        yield $emit($eventOrPromise);
                        $generator->next();
                    }
                }
            });

            $events = [];
            $eventData = [];

            while (yield $iterator->advance()) {
                $event = $iterator->getCurrent();

                $events[] = $event;
                $eventData[] = $specification->mapToEventData($event);
            }

            yield $eventStore->appendToStreamAsync(
                $specification->streamName(),
                $specification->expectedVersion(),
                $eventData
            );

            return ImmList(...$events);
        });
    };
}

const stateResolver = 'Prooph\Micro\Kernel\stateResolver';

function stateResolver(EventStoreConnection $eventStore, CommandSpecification $specification, int $readBatchSize): Closure
{
    return function () use ($eventStore, $specification, $readBatchSize): Promise {
        return call(function () use ($eventStore, $specification, $readBatchSize): Generator {
            $events = [];

            do {
                $slice = yield $eventStore->readStreamEventsForwardAsync(
                    $specification->streamName(),
                    0,
                    $readBatchSize,
                );
                \assert($slice instanceof StreamEventsSlice);

                switch ($slice->status()->value()) {
                    case SliceReadStatus::STREAM_NOT_FOUND:
                        throw new NotFound('Stream not found');
                    case SliceReadStatus::STREAM_DELETED:
                        throw new NotFound('Stream deleted');
                }

                $events = \array_merge($events, $slice->events());
            } while (! $slice->isEndOfStream() && \count($slice->events()) === $readBatchSize);

            return $specification->reconstituteFromHistory(ImmList(...$events));
        });
    };
}
