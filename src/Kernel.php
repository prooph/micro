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
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalEventStore;
use Prooph\Micro\AggregateDefiniton;
use Prooph\Micro\Functional as f;
use Prooph\SnapshotStore\SnapshotStore;
use RuntimeException;
use Throwable;

const buildCommandDispatcher = 'Prooph\Micro\Kernel\buildCommandDispatcher';

/**
 * builds a dispatcher to return a function that receives a messages and return the state
 *
 * usage:
 * $dispatch = buildDispatcher($commandMap, $eventStoreFactory, $snapshotStoreFactory);
 * $state = $dispatch($message);
 *
 * $producerFactory is expected to be a callback that returns an instance of Prooph\ServiceBus\Async\MessageProducer.
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
    array $commandMap,
    callable $eventStoreFactory,
    callable $snapshotStoreFactory = null
): callable {
    return function (Message $message) use (
        $commandMap,
        $eventStoreFactory,
        $snapshotStoreFactory
    ) {
        $getDefinition = function (Message $message) use ($commandMap): AggregateDefiniton {
            return getAggregateDefinition($message, $commandMap);
        };

        $stateResolver = function () use ($message, $getDefinition, $eventStoreFactory, $snapshotStoreFactory) {
            $definition = $getDefinition($message);

            if (null === $snapshotStoreFactory) {
                $state = [];
            } else {
                $state = loadState($snapshotStoreFactory(), $message, $definition);
            }

            /* @var AggregateDefiniton $definition */
            $aggregateId = $definition->extractAggregateId($message);

            if (empty($state)) {
                $nextVersion = 1;
            } else {
                $versionKey = $definition->versionName();
                $nextVersion = $state[$versionKey] + 1;
            }

            $events = loadEvents($definition, $aggregateId, $nextVersion, $eventStoreFactory);

            return $definition->reconstituteState($state, $events);
        };

        $handleCommand = function (Message $message) use ($stateResolver, $commandMap): array {
            $handler = getHandler($message, $commandMap);

            $events = $handler($stateResolver, $message);

            if (! is_array($events)) {
                throw new RuntimeException('The handler did not return an array');
            }

            return $events;
        };

        $persistEvents = function (array $events) use ($eventStoreFactory, $message, $getDefinition): array {
            $definition = $getDefinition($message);

            return persistEvents($events, $eventStoreFactory, $definition, $definition->extractAggregateId($message));
        };

        return f\pipe(
            $handleCommand,
            $persistEvents
        )($message);
    };
}

const loadState = 'Prooph\Micro\Kernel\loadState';

function loadState(SnapshotStore $snapshotStore, Message $message, AggregateDefiniton $definiton): array
{
    $aggregate = $snapshotStore->get($definiton->aggregateType(), $definiton->extractAggregateId($message));

    if (! $aggregate) {
        return [];
    }

    return $aggregate->aggregateRoot();
}

const loadEvents = 'Prooph\Micro\Kernel\loadEvents';

function loadEvents(
    AggregateDefiniton $definition,
    string $aggregateId,
    int $nextVersion,
    callable $eventStoreFactory
): Iterator {
    $eventStore = $eventStoreFactory();

    if (! $eventStore instanceof EventStore) {
        throw new RuntimeException('$eventStoreFactory did not return an instance of ' . EventStore::class);
    }

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

const persistEvents = 'Prooph\Micro\Kernel\persistEvents';

function persistEvents(
    array $events,
    callable $eventStoreFactory,
    AggregateDefiniton $definition,
    string $aggregateId
): array {
    $eventStore = $eventStoreFactory();

    if (! $eventStore instanceof EventStore) {
        throw new RuntimeException('$eventStoreFactory did not return an instance of ' . EventStore::class);
    }

    $metadataEnricher = function (Message $event) use ($events, $definition, $aggregateId) {
        $aggregateVersion = $definition->extractAggregateVersion($event);
        $metadataEnricher = $definition->metadataEnricher($aggregateId, $aggregateVersion);

        if (null !== $metadataEnricher) {
            $event = $metadataEnricher->enrich($event);
        }

        return $event;
    };

    $events = array_map($metadataEnricher, $events);

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

    return $events;
}

const getHandler = 'Prooph\Micro\Kernel\getHandler';

function getHandler(Message $message, array $commandMap): callable
{
    if (! array_key_exists($message->messageName(), $commandMap)) {
        throw new RuntimeException(sprintf(
            'Unknown message "%s". Message name not mapped to an aggregate.',
            $message->messageName()
        ));
    }

    return $commandMap[$message->messageName()]['handler'];
}

const getAggregateDefinition = 'Prooph\Micro\Kernel\getAggregateDefinition';

function getAggregateDefinition(Message $message, array $commandMap): AggregateDefiniton
{
    static $cached = [];

    $messageName = $message->messageName();

    if (isset($cached[$messageName])) {
        return $cached[$messageName];
    }

    if (! isset($commandMap[$messageName])) {
        throw new RuntimeException(sprintf('Unknown message %s. Message name not mapped to an aggregate.', $message->messageName()));
    }

    $cached[$messageName] = new $commandMap[$messageName]['definition']();

    return $cached[$messageName];
}
