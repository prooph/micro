<?php

declare(strict_types=1);

namespace ProophExample\Micro\Infrastructure;

use Prooph\Common\Messaging\Message;
use Prooph\EventSourcing\Aggregate\AggregateType;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AbstractAggregateDefiniton;
use ProophExample\Micro\Model\Event\UserNameWasChanged;
use ProophExample\Micro\Model\Event\UserWasRegistered;
use ProophExample\Micro\Model\Event\UserWasRegisteredWithDuplicateEmail;

final class UserAggregateDefinition extends AbstractAggregateDefiniton
{
    public function streamName(string $aggregateId): StreamName
    {
        return new StreamName('user_stream'); // add aggregate id for one stream per aggregate
    }

    public function aggregateType(): AggregateType
    {
        return AggregateType::fromString('user');
    }

    public function identifierName(): string
    {
        return 'id';
    }

    public function apply(array $state, Message $event): array
    {
        return \ProophExample\Micro\Model\User\apply($state, $event);
    }
}
