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
use Prooph\Micro\AggregateResult;
use Prooph\MicroExample\Model\Command\ChangeUserName;
use Prooph\MicroExample\Model\Command\RegisterUser;
use Prooph\MicroExample\Model\Event\UserNameWasChanged;
use Prooph\MicroExample\Model\Event\UserWasRegistered;
use Prooph\MicroExample\Model\Event\UserWasRegisteredWithDuplicateEmail;
use Prooph\MicroExample\Model\UniqueEmailGuard;

const registerUser = 'Prooph\MicroExample\Model\User\registerUser';

function registerUser(array $state, RegisterUser $command, UniqueEmailGuard $guard): AggregateResult
{
    if ($guard->isUnique($command->email())) {
        $event = new UserWasRegistered(array_merge($command->payload(), ['version' => 1]));
    } else {
        $event = new UserWasRegisteredWithDuplicateEmail(array_merge($command->payload(), ['version' => ++$state['version']]));
    }

    return new AggregateResult(apply($state, $event), $event);
}

const changeUserName = 'Prooph\MicroExample\Model\User\changeUserName';

function changeUserName(array $state, ChangeUserName $command): AggregateResult
{
    if (! mb_strlen($command->username()) > 3) {
        throw new InvalidArgumentException('Username too short');
    }

    $event = new UserNameWasChanged(array_merge($command->payload(), ['version' => $state['version'] + 1]));

    return new AggregateResult(apply($state, $event), $event);
}

const apply = 'Prooph\MicroExample\Model\User\apply';

function apply(array $state, Message ...$events): array
{
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
