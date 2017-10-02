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

namespace Prooph\MicroExample\Model\User;

use InvalidArgumentException;
use Prooph\Common\Messaging\Message;
use Prooph\MicroExample\Model\Command\ChangeUserName;
use Prooph\MicroExample\Model\Command\RegisterUser;
use Prooph\MicroExample\Model\Event\UserNameWasChanged;
use Prooph\MicroExample\Model\Event\UserWasRegistered;
use Prooph\MicroExample\Model\Event\UserWasRegisteredWithDuplicateEmail;
use Prooph\MicroExample\Model\UniqueEmailGuard;

const registerUser = '\Prooph\MicroExample\Model\User\registerUser';

function registerUser(callable $stateResolver, RegisterUser $command, UniqueEmailGuard $guard): array
{
    if ($guard->isUnique($command->email())) {
        return [new UserWasRegistered(array_merge($command->payload(), ['version' => 1]))];
    }

    return [new UserWasRegisteredWithDuplicateEmail(array_merge($command->payload(), ['version' => ++$stateResolver()['version']]))];
}

const changeUserName = '\Prooph\MicroExample\Model\User\changeUserName';

function changeUserName(callable $stateResolver, ChangeUserName $command): array
{
    if (! mb_strlen($command->username()) > 3) {
        throw new InvalidArgumentException('Username too short');
    }

    return [new UserNameWasChanged(array_merge($command->payload(), ['version' => ++$stateResolver()['version']]))];
}

const apply = '\Prooph\MicroExample\Model\User\apply';

function apply($state, Message ...$events): array
{
    if (null === $state) {
        $state = [];
    }

    foreach ($events as $event) {
        switch ($event->messageName()) {
            case UserWasRegistered::class:
                $state = array_merge($state, $event->payload(), ['activated' => true]);
                break;
            case UserWasRegisteredWithDuplicateEmail::class:
                $state = array_merge($state, $event->payload(), ['activated' => false, 'blocked_reason' => 'duplicate email']);
                break;
            case UserNameWasChanged::class:
                $state = array_merge($state, $event->payload());
                break;
        }
    }

    return $state;
}
