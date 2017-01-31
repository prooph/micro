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

    /**
     * Returns the key in message payload to identify the aggregate id
     */
    public function identifierName(): string;

    /**
     * Returns the key in message payload and state to identify version number
     */
    public function versionName(): string;

    public function extractAggregateId(Message $message): string;

    public function extractAggregateVersion(Message $message): int;

    public function streamName(string $aggregateId): StreamName;

    public function metadataEnricher(string $aggregateId, int $aggregateVersion): ?MetadataEnricher;

    public function metadataMatcher(string $aggregateId, int $aggregateVersion): ?MetadataMatcher;

    public function reconstituteState(array $state, Iterator $events): array;

    public function apply(array $state, Message ...$events): array;
}
