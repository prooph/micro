<?php
declare(strict_types = 1);

namespace ProophExample\Micro\Model\User;

use Iterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Metadata\Operator;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AggregateResult;
use Prooph\Micro\Fn;
use Prooph\Micro\FunctionalAggregate;
use Prooph\Micro\StreamMatcher;
use ProophExample\Micro\Model\Command\ChangeUserName;
use ProophExample\Micro\Model\Command\RegisterUser;
use ProophExample\Micro\Model\Event\UserNameWasChanged;
use ProophExample\Micro\Model\Event\UserWasRegistered;
use ProophExample\Micro\Model\Event\UserWasRegisteredWithDuplicateEmail;
use ProophExample\Micro\Model\UniqueEmailGuard;

function registerUser(RegisterUser $command, array $state, UniqueEmailGuard $guard): AggregateResult
{
    if ($guard->isUnique($command->email())) {
        $event = new UserWasRegistered($command->payload());
    } else {
        $event = new UserWasRegisteredWithDuplicateEmail($command->payload());
    }

    return new AggregateResult([$event], apply($state, $event));
}

function changeUserName(ChangeUserName $command, array $state): AggregateResult
{
    if(!mb_strlen($command->username()) > 3) {
        throw new \InvalidArgumentException('Username too short');
    }

    $event = new UserNameWasChanged($command->payload());

    return new AggregateResult([$event], apply($state, $event));
}

function apply(array $state, Message ...$events): array
{
    foreach ($events as $event) {
        switch ($event->messageName()) {
            case UserWasRegistered::class:
                return array_merge($state, $event->payload(), ['activated' => true]);
            case UserWasRegisteredWithDuplicateEmail::class:
                return array_merge($state, $event->payload(), ['activated' => false, 'blocked_reason' => 'duplicate email']);
            case UserNameWasChanged::class:
                /* @var UserNameWasChanged $event */
                return array_merge($state, ['name' => $event->username()]);
        }
    }
}
