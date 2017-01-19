<?php

declare(strict_types=1);

namespace Prooph\Micro\Kernel;

use ArrayIterator;
use Iterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AggregateDefiniton;
use Prooph\Micro\AggregateResult;
use Prooph\Micro\Pipe;

/**
 * builds a dispatcher to return a function that receives a messages and return the state
 *
 * usage:
 * $factory = build($eventStore);
 * $factory = build($producerFactory);
 * $dispatch = $factory($commandMap);
 * $state = $dispatch($message);
 *
 * or shorter:
 * $dispatch = build($eventStore)($producerFactory)($commandMap);
 * $state = $dispatch($message);
 *
 * $producerFactory is expected to be a callback that returns an instance of Prooph\ServiceBus\Async\MessageProducer.
 * $commandMap is expected to be an array like this:
 * [
 *     RegisterUser::class => [
 *         'handler' => function (array $state, Message $message) use (&$factories): AggregateResult {
 *             return \ProophExample\Micro\Model\User\registerUser($state, $message, $factories['emailGuard']());
 *         },
 *         'definition' => UserAggregateDefinition::class,
 *     ],
 *     ChangeUserName::class => [
 *         'handler' => '\ProophExample\Micro\Model\User\changeUserName',
 *         'definition' => UserAggregateDefinition::class,
 *     ],
 * ]
 * $message is expected to be an instance of Prooph\Common\Messaging\Message
 */
function build(EventStore $eventStore): callable
{
    return function (callable $producerFactory) use ($eventStore) {
        return function (array $commandMap) use ($producerFactory, $eventStore) {
            return function (Message $message) use ($commandMap, $eventStore, $producerFactory) {

                $definitionCallback = function (Message $message) use ($commandMap): AggregateDefiniton {
                    return getDefinition($message, $commandMap);
                };

                $loadStateCallback = function (AggregateDefiniton $definiton) use ($message): array {
                    return loadState($message, $definiton);
                };

                $loadEventsCallback = function (array $state) use ($message, $definitionCallback, $eventStore): Iterator {
                    $definition = $definitionCallback($message);
                    $aggregateId = $definition->extractAggregateId($message);
                    return loadEvents(
                        $definition->streamName($aggregateId),
                        $definition->metadataMatcher($aggregateId),
                        $eventStore
                    );
                };

                $reconstituteStateCallback = function (Iterator $events) use ($message, $definitionCallback) {
                    $definition = $definitionCallback($message);
                    return $definition->reconstituteState($events);
                };

                $handlerCallback = function (array $state) use ($message, $commandMap): AggregateResult {
                    $handler = getHandler($message, $commandMap);

                    $aggregateResult = $handler($state, $message);

                    if (! $aggregateResult instanceof AggregateResult) {
                        throw new \RuntimeException('Invalid aggregate result returned');
                    }

                    return $aggregateResult;
                };

                $persistEventsCallback = function (AggregateResult $aggregateResult) use ($eventStore, $message, $definitionCallback): AggregateResult {
                    $definition = $definitionCallback($message);
                    return persistEvents($aggregateResult, $eventStore, $definition, $definition->extractAggregateId($message));
                };

                $publishEventsCallback = function(AggregateResult $aggregateResult) use ($producerFactory) {
                    return publishEvents($aggregateResult, $producerFactory);
                };

                return (new Pipe($message))
                    ->pipe($definitionCallback)
                    ->pipe($loadStateCallback)
                    ->pipe($loadEventsCallback)
                    ->pipe($reconstituteStateCallback)
                    ->pipe($handlerCallback)
                    ->pipe($persistEventsCallback)
                    ->pipe($publishEventsCallback)
                    ->result();
            };
        };
    };
}

function loadState(Message $message, AggregateDefiniton $definiton): array
{
    return []; // @todo: fetch from projections
}

function loadEvents(
    StreamName $streamName,
    ?MetadataMatcher $metadataMatcher,
    EventStore $eventStore,
    int $fromVersion = 1,
    array $state = []
): Iterator {
    if ($eventStore->hasStream($streamName)) {
        return $eventStore->load($streamName, $fromVersion, null, $metadataMatcher)->streamEvents();
    }

    return new ArrayIterator();
}

function persistEvents(AggregateResult $aggregateResult, EventStore $eventStore, AggregateDefiniton $definition, string $aggregateId): AggregateResult
{
    $events = $aggregateResult->raisedEvents();

    if ($metadataEnricher = $definition->metadataEnricher($aggregateId)) {
        $events = array_map([$metadataEnricher, 'enrich'], $events);
    }

    $streamName = $definition->streamName($aggregateId);

    if ($eventStore->hasStream($streamName)) {
        $eventStore->appendTo($streamName, new \ArrayIterator($events));
    } else {
        $eventStore->create(new Stream($streamName, new \ArrayIterator($events)));
    }

    return new AggregateResult($events, $aggregateResult->state());
}

function publishEvents(AggregateResult $aggregateResult, callable $producerCallback): array
{
    $producer = null;
    foreach ($aggregateResult->raisedEvents() as $event) {
        if (null === $producer) {
            $producer = $producerCallback();
        }
        $producer($event);
    }

    return $aggregateResult->state();
}

function getHandler(Message $message, array $commandMap): callable
{
    if (! array_key_exists($message->messageName(), $commandMap)) {
        throw new \RuntimeException(sprintf("Unknown message %s. Message name not mapped to an aggregate.", $message->messageName()));
    }

    return $commandMap[$message->messageName()]['handler'];
}

function getDefinition(Message $message, array $commandMap): AggregateDefiniton
{
    if (! array_key_exists($message->messageName(), $commandMap)) {
        throw new \RuntimeException(sprintf('Unknown message %s. Message name not mapped to an aggregate.', $message->messageName()));
    }

    return new $commandMap[$message->messageName()]['definition']();
}
