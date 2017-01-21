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

namespace ProophTest\Micro\TestAsset;

use Iterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AggregateDefiniton;

final class TestAggregateDefinition implements AggregateDefiniton
{
    public function identifierName(): string
    {
        return 'id';
    }

    public function aggregateType(): string
    {
        return 'test';
    }

    public function versionName(): string
    {
        return 'version';
    }

    public function extractAggregateId(Message $message): string
    {
        return 'some_id';
    }

    public function streamName(string $aggregateId): StreamName
    {
        return new StreamName('foo');
    }

    public function metadataEnricher(string $aggregateId): ?MetadataEnricher
    {
        return null;
    }

    public function metadataMatcher(string $aggregateId): ?MetadataMatcher
    {
        return null;
    }

    public function reconstituteState(Iterator $events): array
    {
        return [];
    }

    public function apply(array $state, Message ...$events): array
    {
        return [];
    }
}
