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

use Iterator;
use Phunkie\Types\Function1;
use Phunkie\Types\ImmList;
use Phunkie\Validation\Failure;
use Phunkie\Validation\Success;
use Phunkie\Validation\Validation;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Exception\ConcurrencyException;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalEventStore;
use Prooph\Micro\AggregateDefinition;
use Prooph\SnapshotStore\SnapshotStore;
use RuntimeException;

const buildCommandDispatcher = '\\Prooph\\Micro\\Kernel\\buildCommandDispatcher';

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
            return function (Message $message) use ($eventStore, $snapshotStore, $commandMap): Validation {
                /* @var AggregateDefinition $definition */
                $definition = getAggregateDefinition($commandMap)($message);

                $aggregateId = $definition->extractAggregateId($message);

                $stateResolver = function () use ($message, $definition, $eventStore, $snapshotStore, $aggregateId): array {
                    if (null === $snapshotStore) {
                        $state = [];
                    } else {
                        $state = loadState($snapshotStore)($definition)($message);
                    }

                    if (empty($state)) {
                        $nextVersion = 1;
                    } else {
                        $versionKey = $definition->versionName();
                        $nextVersion = $state[$versionKey] + 1;
                    }

                    $events = loadEvents($eventStore)($definition)($aggregateId)($nextVersion);

                    return $definition->reconstituteState($state, $events->iterator());
                };

                $handleCommand = function (Message $message) use ($stateResolver, $commandMap): ImmList {
                    $handler = getHandler($commandMap)($message);

                    $events = $handler($stateResolver, $message);

                    if (! is_array($events)) {
                        throw new RuntimeException('The handler did not return an array');
                    }

                    return ImmList(...$events);
                };

                $enrichEvents = function (ImmList $events) use ($definition, $aggregateId): ImmList {
                    return enrichEvents($definition)($aggregateId)($events);
                };

                $persistEvents = function (ImmList $events) use ($eventStore, $definition, $message): Validation {
                    return persistEvents($eventStore)($definition)($definition->extractAggregateId($message))($events);
                };

                return Function1($handleCommand)
                    ->andThen($enrichEvents)
                    ->andThen($persistEvents)
                    ->run($message);
            };
        };
    };
}

const loadState = '\\Prooph\\Micro\\Kernel\\loadState';

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
}

const loadEvents = '\\Prooph\\Micro\\Kernel\\loadEvents';

function loadEvents(
    EventStore $eventStore
): callable {
    return function (AggregateDefinition $definition) use ($eventStore): callable {
        return function (string $aggregateId) use ($eventStore, $definition): callable {
            return function (int $nextVersion) use ($eventStore, $definition, $aggregateId): ImmList {
                $streamName = $definition->streamName();
                $metadataMatcher = $definition->metadataMatcher($aggregateId, $nextVersion);

                if (! $eventStore->hasStream($streamName)) {
                    return Nil();
                }

                if ($definition->hasOneStreamPerAggregate()) {
                    $streamName = new StreamName($streamName->toString() . '-' . $aggregateId); // append aggregate id to stream name
                } else {
                    $nextVersion = 1; // we don't know the event position, the metadata matcher will help, we start at 1
                }

                return ImmList(...$eventStore->load($streamName, $nextVersion, null, $metadataMatcher));
            };
        };
    };
}

const enrichEvents = '\\Prooph\\Micro\\Kernel\\enrichEvents';

function enrichEvents(AggregateDefinition $definition): callable
{
    return function (string $aggregateId) use ($definition): callable {
        return function (ImmList $events) use ($definition, $aggregateId) {
            return $events->map(function ($event) use ($definition, $aggregateId): Message {
                $aggregateVersion = $definition->extractAggregateVersion($event);
                $metadataEnricher = $definition->metadataEnricher($aggregateId, $aggregateVersion);

                if (null !== $metadataEnricher) {
                    $event = $metadataEnricher->enrich($event);
                }

                return $event;
            });
        };
    };
}

const persistEvents = '\\Prooph\\Micro\\Kernel\\persistEvents';

function persistEvents(
    EventStore $eventStore
): callable {
    return function (AggregateDefinition $definition) use ($eventStore): callable {
        return function (string $aggregateId) use ($eventStore, $definition): callable {
            return function (ImmList $events) use ($eventStore, $definition, $aggregateId): Validation {
                $streamName = $definition->streamName();

                if ($definition->hasOneStreamPerAggregate()) {
                    $streamName = new StreamName($streamName->toString() . '-' . $aggregateId); // append aggregate id to stream name
                }

                if ($eventStore instanceof TransactionalEventStore) {
                    $eventStore->beginTransaction();
                }

                try {
                    if ($eventStore->hasStream($streamName)) {
                        $eventStore->appendTo($streamName, $events->iterator());
                    } else {
                        $eventStore->create(new Stream($streamName, $events->iterator()));
                    }
                } catch (\Throwable $e) {
                    if ($eventStore instanceof TransactionalEventStore) {
                        $eventStore->rollback();
                    }

                    if ($e instanceof ConcurrencyException) {
                        return Failure($e);
                    }

                    throw $e;
                }

                if ($eventStore instanceof TransactionalEventStore) {
                    $eventStore->commit();
                }

                return Success(null);
            };
        };
    };
}

const getHandler = '\\Prooph\\Micro\\Kernel\\getHandler';

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

const getAggregateDefinition = '\\Prooph\\Micro\\Kernel\\getAggregateDefinition';

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
