<?php

declare(strict_types=1);

namespace Prooph\Micro;

use Iterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Metadata\Operator;

abstract class AbstractAggregateDefiniton implements AggregateDefiniton
{
    public function identifierName(): string
    {
        return 'id';
    }

    public function extractAggregateId(Message $message): string
    {
        $idProperty = $this->identifierName();

        if (! array_key_exists($idProperty, $message->payload())) {
            throw new \RuntimeException(sprintf(
                'Missing aggregate id %s in command payload of command %s. Payload was %s',
                $idProperty,
                $message->messageName(),
                json_encode($message->payload())
            ));
        }

        return $message->payload()[$idProperty];
    }

    public function metadataMatcher(string $aggregateId): ?MetadataMatcher
    {
        return (new MetadataMatcher())->withMetadataMatch('_aggregate_id', Operator::EQUALS(), $aggregateId);
    }

    public function metadataEnricher(string $aggregateId): ?MetadataEnricher
    {
        return new class($aggregateId) implements MetadataEnricher {
            private $id;

            public function __construct(string $id)
            {
                $this->id = $id;
            }

            public function enrich(Message $message): Message
            {
                return $message->withAddedMetadata('_aggregate_id', $this->id);
            }
        };
    }

    public function reconstituteState(Iterator $events): array
    {
        $state = [];

        foreach ($events as $event) {
            $state = $this->apply($state, $event);
        }

        return $state;
    }
}
