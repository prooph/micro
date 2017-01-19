<?php

declare(strict_types=1);

namespace Prooph\MicroExample\Model\User;

use Prooph\Common\Messaging\Message;
use Prooph\Micro\AggregateResult;
use Prooph\MicroExample\Model\Command\ChangeUserName;
use Prooph\MicroExample\Model\Command\RegisterUser;
use Prooph\MicroExample\Model\Event\UserNameWasChanged;
use Prooph\MicroExample\Model\Event\UserWasRegistered;
use Prooph\MicroExample\Model\Event\UserWasRegisteredWithDuplicateEmail;
use Prooph\MicroExample\Model\UniqueEmailGuard;

function registerUser(array $state, RegisterUser $command, UniqueEmailGuard $guard): AggregateResult
{
    if ($guard->isUnique($command->email())) {
        $event = new UserWasRegistered($command->payload());
    } else {
        $event = new UserWasRegisteredWithDuplicateEmail($command->payload());
    }

    return new AggregateResult([$event], apply($state, $event));
}

function changeUserName(array $state, ChangeUserName $command): AggregateResult
{
    if (! mb_strlen($command->username()) > 3) {
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
