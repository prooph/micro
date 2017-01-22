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
        $event = new UserWasRegistered($command->payload());
    } else {
        $event = new UserWasRegisteredWithDuplicateEmail($command->payload());
    }

    return new AggregateResult([$event], apply($state, $event));
}

const changeUserName = 'Prooph\MicroExample\Model\User\changeUserName';

function changeUserName(array $state, ChangeUserName $command): AggregateResult
{
    if (! mb_strlen($command->username()) > 3) {
        throw new InvalidArgumentException('Username too short');
    }

    $event = new UserNameWasChanged($command->payload());

    return new AggregateResult([$event], apply($state, $event));
}

const apply = 'Prooph\MicroExample\Model\User\apply';

function apply(array $state, Message ...$events): array
{
    foreach ($events as $event) {
        switch ($event->messageName()) {
            case UserWasRegistered::class:
                $state['version'] = 1;

                return array_merge($state, $event->payload(), ['activated' => true]);
            case UserWasRegisteredWithDuplicateEmail::class:
                $state = array_merge($state, $event->payload(), ['activated' => false, 'blocked_reason' => 'duplicate email']);
                ++$state['version'];

                return $state;
            case UserNameWasChanged::class:
                /* @var UserNameWasChanged $event */
                $state = array_merge($state, ['name' => $event->username()]);
                ++$state['version'];

                return $state;
        }
    }
}
