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
use Phunkie\Types\ImmList;
use Phunkie\Types\Kind;
use Phunkie\Validation\Validation;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalEventStore;
use Prooph\Micro\AggregateDefinition;
use Prooph\SnapshotStore\SnapshotStore;
use RuntimeException;

const buildCommandDispatcher = '\\Prooph\\Micro\\Kernel\\buildCommandDispatcher';
/**
 * builds a dispatcher and returns a function that receives a messages and returns Success | Failure
 *
 * usage:
 * $dispatch = buildDispatcher($eventStore, $commandMap, $snapshotStore, $publisher);
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
function buildCommandDispatcher(
    EventStore $eventStore,
    array $commandMap,
    SnapshotStore $snapshotStore = null,
    callable $publisher = null
): callable {
    return function (Message $message) use ($eventStore, $snapshotStore, $commandMap, $publisher): Validation {
        try {
            $definition = getAggregateDefinition($message, $commandMap);
            $aggregateId = $definition->extractAggregateId($message);
        } catch (\Throwable $e) {
            return Failure($e);
        }

        $stateResolver = function () use ($message, $definition, $eventStore, $snapshotStore, $aggregateId): array {
            $state = loadState($message, $definition, $snapshotStore);

            $nextVersion = empty($state) ? 1 : $state[$definition->versionName()] + 1;

            $events = loadEvents($eventStore, $definition, $aggregateId, $nextVersion);

            return $definition->reconstituteState($state, $events);
        };

        $handleCommand = function (Message $message) use ($stateResolver, $commandMap): ImmList {
            $handler = getHandler($message, $commandMap);

            $events = $handler($stateResolver, $message);

            if (! is_array($events)) {
                throw new \RuntimeException('The command handler did not return an array');
            }

            return ImmList(...$events);
        };

        $enrichEvents = function (ImmList $events) use ($message, $definition, $aggregateId): Kind {
            $enricher = getEnricherFor($definition, $aggregateId, $message);

            return $events->map($enricher);
        };

        $persistEvents = function (ImmList $events) use ($eventStore, $definition, $message, $aggregateId): Kind {
            return persistEvents($events, $eventStore, $definition, $aggregateId);
        };

        $publishEvents = function (ImmList $events) use ($publisher): Kind {
            if ($events->isEmpty() || null === $publisher) {
                return $events;
            }

            return $events->map($publisher);
        };

        $pipe = function () use ($message, $handleCommand, $enrichEvents, $persistEvents, $publishEvents) {
            return Function1($handleCommand)
                ->andThen($enrichEvents)
                ->andThen($persistEvents)
                ->andThen($publishEvents)
                ->run($message);
        };

        return Attempt($pipe);
    };
}

const loadState = '\\Prooph\\Micro\\Kernel\\loadState';

function loadState(Message $message, AggregateDefinition $definition, SnapshotStore $snapshotStore = null): array
{
    if (null === $snapshotStore) {
        return [];
    }

    $aggregate = $snapshotStore->get($definition->aggregateType(), $definition->extractAggregateId($message));

    if (! $aggregate) {
        return [];
    }

    return $aggregate->aggregateRoot();
}

const loadEvents = '\\Prooph\\Micro\\Kernel\\loadEvents';

function loadEvents(
    EventStore $eventStore,
    AggregateDefinition $definition,
    string $aggregateId,
    int $nextVersion
): Iterator {
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
}

const getEnricherFor = '\\Prooph\\Micro\\Kernel\\getEnricherFor';

function getEnricherFor(AggregateDefinition $definition, string $aggregateId, Message $message): callable
{
    return function (Message $event) use ($definition, $aggregateId, $message): Message {
        $aggregateVersion = $definition->extractAggregateVersion($event);
        $metadataEnricher = $definition->metadataEnricher($aggregateId, $aggregateVersion, $message);

        if (null !== $metadataEnricher) {
            $event = $metadataEnricher->enrich($event);
        }

        return $event;
    };
}

const persistEvents = '\\Prooph\\Micro\\Kernel\\persistEvents';

function persistEvents(
    ImmList $events,
    EventStore $eventStore,
    AggregateDefinition $definition,
    string $aggregateId
): Kind {
    if ($events->isEmpty()) {
        return $events;
    }

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

        throw $e;
    }

    if ($eventStore instanceof TransactionalEventStore) {
        $eventStore->commit();
    }

    return $events;
}

const getHandler = '\\Prooph\\Micro\\Kernel\\getHandler';

function getHandler(Message $m, array $c): callable
{
    $n = $m->messageName();

    if (! array_key_exists($n, $c)) {
        throw new RuntimeException(sprintf(
            'Unknown message "%s". Message name not mapped to an aggregate.',
            $n
        ));
    }

    return $c[$n]['handler'];
}

const getAggregateDefinition = '\\Prooph\\Micro\\Kernel\\getAggregateDefinition';

function getAggregateDefinition(Message $m, array $c): AggregateDefinition
{
    $n = $m->messageName();

    if (! isset($c[$m->messageName()])) {
        throw new RuntimeException(sprintf('Unknown message %s. Message name not mapped to an aggregate.', $n));
    }

    return new $c[$n]['definition']();
}
