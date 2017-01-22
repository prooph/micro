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
use Prooph\EventStore\StreamName;

interface AggregateDefiniton
{
    public function aggregateType(): string;

    public function identifierName(): string;

    public function versionName(): string;

    public function extractAggregateId(Message $message): string;

    public function extractAggregateVersion(array $state): int;

    public function streamName(string $aggregateId): StreamName;

    public function metadataEnricher(string $aggregateId, int $aggregateVersion): ?MetadataEnricher;

    public function metadataMatcher(string $aggregateId, int $aggregateVersion): ?MetadataMatcher;

    public function reconstituteState(Iterator $events): array;

    public function apply(array $state, Message ...$events): array;
}
