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

use ArrayIterator;
use Iterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AggregateDefiniton;
use Prooph\Micro\AggregateResult;
use Prooph\SnapshotStore\SnapshotStore;
use RuntimeException;
use Throwable;

const buildCommandDispatcher = 'Prooph\Micro\Kernel\buildCommandDispatcher';

/**
 * builds a dispatcher to return a function that receives a messages and return the state
 *
 * usage:
 * $dispatch = buildDispatcher($eventStoreFactory, $snapshotStoreFactory, $aggregateDefinition, $handlerMap, $producerFactory);
 * $state = $dispatch($message);
 *
 * $producerFactory is expected to be a callback that returns an instance of Prooph\ServiceBus\Async\MessageProducer.
 * $commandMap is expected to be an array like this:
 * [
 *     RegisterUser::class => function (array $state, Message $message) use (&$factories): AggregateResult {
 *         return \Prooph\MicroExample\Model\User\registerUser($state, $message, $factories['emailGuard']());
 *     },
 *     ChangeUserName::class => \Prooph\MicroExample\Model\User\changeUserName,
 * ]
 * $message is expected to be an instance of Prooph\Common\Messaging\Message
 */
function buildCommandDispatcher(
    callable $eventStoreFactory,
    callable $snapshotStoreFactory,
    AggregateDefiniton $aggregateDefiniton,
    array $handlerMap,
    callable $producerFactory,
    callable $startProducerTransaction = null,
    callable $commitProducerTransaction = null
): callable {
    return function (Message $message) use (
        $eventStoreFactory,
        $snapshotStoreFactory,
        $aggregateDefiniton,
        $handlerMap,
        $producerFactory,
        $startProducerTransaction,
        $commitProducerTransaction
    ) {
        $loadState = function (Message $message) use ($aggregateDefiniton, $snapshotStoreFactory): array {
            return loadState($snapshotStoreFactory(), $message, $aggregateDefiniton);
        };

        $loadEvents = function (array $state) use ($message, $aggregateDefiniton, $eventStoreFactory): Iterator {
            $aggregateId = $aggregateDefiniton->extractAggregateId($message);
            if (empty($state)) {
                $nextVersion = 1;
            } else {
                $nextVersion = $aggregateDefiniton->extractAggregateVersion($state) + 1;
            }

            return loadEvents(
                $aggregateDefiniton->streamName($aggregateId),
                $aggregateDefiniton->metadataMatcher($aggregateId, $nextVersion),
                $eventStoreFactory
            );
        };

        $reconstituteState = function (Iterator $events) use ($message, $aggregateDefiniton): array {
            return $aggregateDefiniton->reconstituteState($events);
        };

        $handleCommand = function (array $state) use ($message, $handlerMap): AggregateResult {
            $handler = getHandler($message, $handlerMap);

            $aggregateResult = $handler($state, $message);

            if (! $aggregateResult instanceof AggregateResult) {
                throw new RuntimeException('Invalid aggregate result returned');
            }

            return $aggregateResult;
        };

        $persistEvents = function (AggregateResult $aggregateResult) use (
            $eventStoreFactory,
            $message,
            $aggregateDefiniton
        ): AggregateResult {
            return persistEvents(
                $aggregateResult,
                $eventStoreFactory,
                $aggregateDefiniton,
                $aggregateDefiniton->extractAggregateId($message)
            );
        };

        $publishEvents = function (AggregateResult $aggregateResult) use (
            $producerFactory,
            $startProducerTransaction,
            $commitProducerTransaction
        ): AggregateResult {
            return publishEvents($aggregateResult, $producerFactory, $startProducerTransaction, $commitProducerTransaction);
        };

        return pipleline(
            $loadState,
            $loadEvents,
            $reconstituteState,
            $handleCommand,
            $persistEvents,
            $publishEvents,
            aggregateState
        )($message);
    };
}

const pipeline = 'Prooph\Micro\Kernel\pipeline';

function pipleline(callable $firstCallback, callable ...$callbacks): callable
{
    array_unshift($callbacks, $firstCallback);

    return function ($value = null) use ($callbacks) {
        try {
            $result = array_reduce($callbacks, function ($accumulator, callable $callback) {
                return $callback($accumulator);
            }, $value);
        } catch (Throwable $e) {
            return $e;
        }

        return $result;
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
    StreamName $streamName,
    ?MetadataMatcher $metadataMatcher,
    callable $eventStoreFactory
): Iterator {
    $eventStore = $eventStoreFactory();

    if ($eventStore->hasStream($streamName)) {
        return $eventStore->load($streamName, 1, null, $metadataMatcher)->streamEvents();
    }

    return new ArrayIterator();
}

const persistEvents = 'Prooph\Micro\Kernel\persistEvents';

function persistEvents(
    AggregateResult $aggregateResult,
    callable $eventStoreFactory,
    AggregateDefiniton $definition,
    string $aggregateId
): AggregateResult {
    $events = $aggregateResult->raisedEvents();

    $metadataEnricher = function (Message $event) use ($aggregateResult, $definition, $aggregateId) {
        $aggregateVersion = $definition->extractAggregateVersion($aggregateResult->state());
        $metadataEnricher = $definition->metadataEnricher($aggregateId, $aggregateVersion);

        if (null !== $metadataEnricher) {
            $event = $metadataEnricher->enrich($event);
        }

        return $event;
    };

    $events = array_map($metadataEnricher, $events);

    $streamName = $definition->streamName($aggregateId);

    $eventStore = $eventStoreFactory();

    if ($eventStore->hasStream($streamName)) {
        $eventStore->appendTo($streamName, new \ArrayIterator($events));
    } else {
        $eventStore->create(new Stream($streamName, new \ArrayIterator($events)));
    }

    return new AggregateResult($aggregateResult->state(), ...$events);
}

const publishEvents = 'Prooph\Micro\Kernel\publishEvents';

function publishEvents(
    AggregateResult $aggregateResult,
    callable $producerCallback,
    callable $startProducerTransaction = null,
    callable $commitProducerTransaction = null
): AggregateResult {
    $producer = null;
    $commit = false;

    if (null !== $startProducerTransaction && null !== $commitProducerTransaction) {
        $commit = true;
        $startProducerTransaction();
    }

    foreach ($aggregateResult->raisedEvents() as $event) {
        if (null === $producer) {
            $producer = $producerCallback();
        }
        $producer($event);
    }

    if ($commit) {
        $commitProducerTransaction();
    }

    return $aggregateResult;
}

const aggregateState = 'Prooph\Micro\Kernel\aggregateState';

function aggregateState(AggregateResult $aggregateResult): array
{
    return $aggregateResult->state();
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

    return $commandMap[$message->messageName()];
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
        throw new RuntimeException(sprintf(
            'Unknown message "%s". Message name not mapped to an aggregate.',
            $message->messageName()
        ));
    }

    $cached[$messageName] = new $commandMap[$messageName]['definition']();

    return $cached[$messageName];
}
