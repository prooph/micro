<?php

declare(strict_types=1);

namespace Prooph\Micro;

use Iterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\StreamName;

interface AggregateDefiniton
{
    public function identifierName(): string;

    public function aggregateType(): AggregateType;

    public function streamName(string $aggregateId): StreamName;

    public function metadataEnricher(string $aggregateId): ?MetadataEnricher;

    public function metadataMatcher(string $aggregateId): ?MetadataMatcher;

    public function reconstituteState(Iterator $events): array;

    public function apply(array $state, Message $event): array;
}
