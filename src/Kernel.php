<?php
/**
 * This file is part of the prooph/micro.
 * (c) 2017-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2017-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\Micro\Kernel;

use EmptyIterator;
use Iterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Exception\ConcurrencyException;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalEventStore;
use Prooph\Micro\AggregateDefinition;
use Prooph\Micro\Functional as f;
use Prooph\SnapshotStore\SnapshotStore;
use RuntimeException;

const buildCommandDispatcher = 'Prooph\Micro\Kernel\buildCommandDispatcher';

/**
 * builds a dispatcher to return a function that receives a messages and return the state
 *
 * usage:
 * $dispatch = buildDispatcher($eventStore)($snapshotStore)($commandMap);
 * $attempt = $dispatch($message);
 *
 * $commandMap is expected to be an array like this:
 * [
 *     RegisterUser::class => [
 *         'handler' => function (array $state, Message $message) use (&$factories): AggregateResult {
 *             return \Prooph\MicroExample\Model\User\registerUser($state, $message, $factories['emailGuard']());
 *         },
 *         'definition' => UserAggregateDefinition::class,
 *     ],
 *     ChangeUserName::class => [
 *         'handler' => '\Prooph\MicroExample\Model\User\changeUserName',
 *         'definition' => UserAggregateDefinition::class,
 *     ],
 * ]
 * $message is expected to be an instance of Prooph\Common\Messaging\Message
 */
function buildCommandDispatcher(EventStore $eventStore): callable
{
    return function (SnapshotStore $snapshotStore = null) use ($eventStore): callable {
        return function (array $commandMap) use ($eventStore, $snapshotStore): callable {
            return function (Message $message) use ($eventStore, $snapshotStore, $commandMap): f\Attempt {
                /* @var AggregateDefinition $definition */
                $definition = getAggregateDefinition($commandMap)($message);

                $stateResolver = function () use ($message, $definition, $eventStore, $snapshotStore): array {
                    if (null === $snapshotStore) {
                        $state = [];
                    } else {
                        $state = loadState($snapshotStore)($definition)($message);
                    }

                    $aggregateId = $definition->extractAggregateId($message);

                    if (empty($state)) {
                        $nextVersion = 1;
                    } else {
                        $versionKey = $definition->versionName();
                        $nextVersion = $state[$versionKey] + 1;
                    }

                    $events = loadEvents($eventStore)($definition)($aggregateId)($nextVersion);

                    return $definition->reconstituteState($state, $events);
                };

                $handleCommand = function (Message $message) use ($stateResolver, $commandMap): array {
                    $handler = getHandler($commandMap)($message);

                    $events = $handler($stateResolver, $message);

                    if (! is_array($events)) {
                        throw new RuntimeException('The handler did not return an array');
                    }

                    return $events;
                };

                $persistEvents = function (array $events) use ($eventStore, $definition, $message): void {
                    persistEvents($eventStore)($definition)($definition->extractAggregateId($message))($events);
                };

                try {
                    f\pipe([
                        $handleCommand,
                        $persistEvents,
                    ])($message);
                } catch (ConcurrencyException $e) {
                    return f\Attempt::failure('Concurrency exception');
                }

                return f\Attempt::success();
            };
        };
    };

//    return f\curry(SnapshtoStore $snapshotStore = null, array $commandMap, Message $message): f\Attempt {
//        ...
//
}

const loadState = 'Prooph\Micro\Kernel\loadState';

function loadState(SnapshotStore $snapshotStore): callable
{
    return function (AggregateDefinition $definition) use ($snapshotStore) {
        return function (Message $message) use ($snapshotStore, $definition): array {
            $aggregate = $snapshotStore->get($definition->aggregateType(), $definition->extractAggregateId($message));

            if (! $aggregate) {
                return [];
            }

            return $aggregate->aggregateRoot();
        };
    };

//    return f\curry(function (AggregateDefinition $definiton, Message $message) use ($snapshotStore): array {
//        ...
//    });
}

const loadEvents = 'Prooph\Micro\Kernel\loadEvents';

function loadEvents(
    EventStore $eventStore
): callable {
    return function (AggregateDefinition $definition) use ($eventStore): callable {
        return function (string $aggregateId) use ($eventStore, $definition): callable {
            return function (int $nextVersion) use ($eventStore, $definition, $aggregateId): Iterator {
                $streamName = $definition->streamName();
                $metadataMatcher = $definition->metadataMatcher($aggregateId, $nextVersion);

                if (! $eventStore->hasStream($streamName)) {
                    return new EmptyIterator();
                }

                if ($definition->hasOneStreamPerAggregate()) {
                    $streamName = new StreamName($streamName->toString() . '-' . $aggregateId); // append aggregate id to stream name
                } else {
                    $nextVersion = 1; // we don't know the event position, the metadata matcher will help, we start at 1
                }

                return $eventStore->load($streamName, $nextVersion, null, $metadataMatcher);
            };
        };
    };

//    return f\curry(function (AggregateDefinition $definition, string $aggregateId, int $nextVersion) use ($eventStore): Iterator {
//         ...
//    });
}

const persistEvents = 'Prooph\Micro\Kernel\persistEvents';

function persistEvents(
    EventStore $eventStore
): callable {
    return function (AggregateDefinition $definition) use ($eventStore): callable {
        return function (string $aggregateId) use ($eventStore, $definition): callable {
            return function (array $events) use ($eventStore, $definition, $aggregateId): void {
                $metadataEnricher = f\map(function (Message $event) use ($events, $definition, $aggregateId) {
                    $aggregateVersion = $definition->extractAggregateVersion($event);
                    $metadataEnricher = $definition->metadataEnricher($aggregateId, $aggregateVersion);

                    if (null !== $metadataEnricher) {
                        $event = $metadataEnricher->enrich($event);
                    }

                    return $event;
                });

                $events = $metadataEnricher($events);

                $streamName = $definition->streamName();

                if ($definition->hasOneStreamPerAggregate()) {
                    $streamName = new StreamName($streamName->toString() . '-' . $aggregateId); // append aggregate id to stream name
                }

                if ($eventStore instanceof TransactionalEventStore) {
                    $eventStore->beginTransaction();
                }

                try {
                    if ($eventStore->hasStream($streamName)) {
                        $eventStore->appendTo($streamName, new \ArrayIterator($events));
                    } else {
                        $eventStore->create(new Stream($streamName, new \ArrayIterator($events)));
                    }
                } catch (\Throwable $e) {
                    if ($eventStore instanceof TransactionalEventStore) {
                        $eventStore->rollback();
                    }

                    throw $e;
                }

                if ($eventStore instanceof TransactionalEventStore) {
                    $eventStore->commit();
                }
            };
        };
    };

//    return f\curry(function (AggregateDefinition $definition, string $aggregateId, array $events) use ($eventStore): void {
//       ...
//    });
}

const getHandler = 'Prooph\Micro\Kernel\getHandler';

function getHandler(array $c): callable
{
    return function (Message $m) use ($c) {
        $n = $m->messageName();

        if (! array_key_exists($n, $c)) {
            throw new RuntimeException(sprintf(
                'Unknown message "%s". Message name not mapped to an aggregate.',
                $n
            ));
        }

        return $c[$n]['handler'];
    };
}

const getAggregateDefinition = 'Prooph\Micro\Kernel\getAggregateDefinition';

function getAggregateDefinition(array $c): callable
{
    return function (Message $m) use ($c): AggregateDefinition {
        $n = $m->messageName();

        if (! isset($c[$m->messageName()])) {
            throw new RuntimeException(sprintf('Unknown message %s. Message name not mapped to an aggregate.', $n));
        }

        return new $c[$n]['definition']();
    };
}
