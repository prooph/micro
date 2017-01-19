<?php

declare(strict_types=1);

namespace Prooph\MicroExample\Infrastructure;

use Prooph\Common\Messaging\Message;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AbstractAggregateDefiniton;

final class UserAggregateDefinition extends AbstractAggregateDefiniton
{
    public function streamName(string $aggregateId): StreamName
    {
        return new StreamName('user_stream'); // add aggregate id for one stream per aggregate
    }

    public function apply(array $state, Message ...$events): array
    {
        return \Prooph\MicroExample\Model\User\apply($state, ...$events);
    }
}
