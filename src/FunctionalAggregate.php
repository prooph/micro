<?php

declare(strict_types = 1);

namespace Prooph\Micro;

use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Stream;

interface FunctionalAggregate
{
    public static function streamMatcher(string $aggregateId): StreamMatcher;

    public static function identifierName(): string;

    public static function reconstituteState(Stream $events): array;

    public static function apply(array $state, Message $event): array;
}
