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

    public function versionName(): string
    {
        return 'version';
    }

    public function extractAggregateId(Message $message): string
    {
        $idProperty = $this->identifierName();

        $payload = $message->payload();

        if (! array_key_exists($idProperty, $payload)) {
            throw new \RuntimeException(sprintf(
                'Missing aggregate id %s in payload of message %s. Payload was %s',
                $idProperty,
                $message->messageName(),
                json_encode($payload)
            ));
        }

        return $payload[$idProperty];
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
