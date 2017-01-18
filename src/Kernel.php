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
use Prooph\ServiceBus\EventBus;

function dispatch(Message $message, array $commandMap, EventStore $eventStore, EventBus $eventBus): array
{
    $definition = getDefinition($message, $commandMap);
    $aggregateId = extractAggregateId($message, $definition);

    return (new Pipe())
        ->pipe(loadEvents(
            $definition->streamName($aggregateId),
            $definition->metadataMatcher($aggregateId),
            $eventStore
        ))
        ->pipe(function (Iterator $events) use ($definition): array {
            return $definition->reconstituteState($events);
        })
        ->pipe(function (array $state) use ($message, $commandMap): AggregateResult {
            $handler = getHandler($message, $commandMap);

            $aggregateResult = $handler($message, $state);

            if (! $aggregateResult instanceof AggregateResult) {
                throw new \RuntimeException('Invalid aggregate result returned');
            }

            return $aggregateResult;
        })
        ->pipe(persistEvents($eventStore, $definition, $aggregateId))
        ->pipe(publishEvents($eventBus))
        ->result();
}

function loadEvents(
    StreamName $streamName,
    ?MetadataMatcher $metadataMatcher,
    EventStore $eventStore,
    int $fromVersion = 1,
    array $state = []
): callable {
    return function () use ($streamName, $metadataMatcher, $eventStore, $fromVersion, $state): Iterator {
        $streamExists = $eventStore->hasStream($streamName);

        if ($streamExists) {
            return $eventStore->load($streamName, $fromVersion, null, $metadataMatcher)->streamEvents();
        }

        return new ArrayIterator();
    };
}

function persistEvents(EventStore $eventStore, AggregateDefiniton $definition, string $aggregateId): callable
{
    return function (AggregateResult $aggregateResult) use ($eventStore, $definition, $aggregateId): AggregateResult {
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
    };
}

function extractAggregateId(Message $message, AggregateDefiniton $aggregateDefiniton): string
{
    $idProperty = $aggregateDefiniton->identifierName();

    if (! array_key_exists($idProperty, $message->payload())) {
        throw new \RuntimeException(sprintf(
            "Missing aggregate id %s in command payload of command %s. Payload was %s",
            $idProperty,
            $message->messageName(),
            json_encode($message->payload())
        ));
    }

    return $message->payload()[$idProperty];
}

function publishEvents(EventBus $eventBus): callable
{
    return function (AggregateResult $aggregateResult) use ($eventBus): array {
        foreach ($aggregateResult->raisedEvents() as $event) {
            $eventBus->dispatch($event);
        }

        return $aggregateResult->state();
    };
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
        throw new \RuntimeException(sprintf("Unknown message %s. Message name not mapped to an aggregate.", $message->messageName()));
    }

    return new $commandMap[$message->messageName()]['definition'];
}
