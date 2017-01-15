<?php

declare(strict_types = 1);

namespace Prooph\Micro;

use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream;
use Prooph\EventStore\Util\Assertion;

final class Fn
{
    public static function connect(array $commandAggregateMap, EventStore $eventStore): callable
    {
        return function(Message $message) use (&$commandAggregateMap, $eventStore): array {
            if (! array_key_exists($message->messageName(), $commandAggregateMap)) {
                throw new \RuntimeException(sprintf("Unknown message %s. Message name not mapped to an aggregate.", $message->messageName()));
            }

            /** @var FunctionalAggregate $aggregateScope */
            list($aggregateScope, $handlerFunc) = $commandAggregateMap[$message->messageName()];

            Assertion::implementsInterface($aggregateScope, FunctionalAggregate::class);

            $streamMatcher = self::getStreamMatcherFromAggregateScope($message, (string)$aggregateScope);

            //Todo: use snapshot store if provided

            $streamExists = $eventStore->hasStream($streamMatcher->streamName());

            if($streamExists) {
                $stream = $eventStore->load($streamMatcher->streamName(), 1, null, $streamMatcher->metadataMatcher());
            } else {
                $stream = new Stream($streamMatcher->streamName(), new \ArrayIterator());
            }

            //Todo: if we deal with snapshots, we should use $aggregateScope::apply
            $state = $aggregateScope::reconstituteState($stream);

            if(is_string($handlerFunc)) {
                $handlerFunc = $aggregateScope . '::' . $handlerFunc;
            }

            /** @var AggregateResult $aggregateResult */
            $aggregateResult = $handlerFunc($message, $state);

            $raisedEvents = $aggregateResult->raisedEvents();

            if($metadataEnricher = $streamMatcher->metadataEnricher()) {
                $raisedEvents = array_map([$metadataEnricher, 'enrich'], $aggregateResult->raisedEvents());
            }

            if ($streamExists) {
                $eventStore->appendTo($streamMatcher->streamName(), new \ArrayIterator($raisedEvents));
            } else {
                $eventStore->create(new Stream($streamMatcher->streamName(), new \ArrayIterator($raisedEvents)));
            }

            return $aggregateResult->state();
        };
    }

    private static function getStreamMatcherFromAggregateScope(Message $message, string $aggregateScope): StreamMatcher
    {
        /** @var FunctionalAggregate $aggregateScope */
        $idProperty = $aggregateScope::identifierName();

        if (!array_key_exists($idProperty, $message->payload())) {
            throw new \RuntimeException(sprintf(
                "Missing aggregate id %s in command payload of command %s. Payload was %s",
                $idProperty,
                $message->messageName(),
                json_encode($message->payload())
            ));
        }

        $aggregateId = $message->payload()[$idProperty];

        return $aggregateScope::streamMatcher($aggregateId);
    }

    public static function assertTargetState(Message $command, array $state, string $identifierKey)
    {
        if (!array_key_exists($identifierKey, $state)) {
            throw new \InvalidArgumentException("Missing $identifierKey key in state");
        }

        if (!array_key_exists($identifierKey, $command->payload())) {
            throw new \InvalidArgumentException("Missing $identifierKey key in command payload. Got command " . $command->messageName());
        }

        if($state[$identifierKey] !== $command->payload()[$identifierKey]) {
            throw new \RuntimeException('Command addresses wrong aggregate. Target id is: '
                . $command->payload()[$identifierKey]
                . ' but state id is: ' . $state[$identifierKey]
            );
        }
    }
}
