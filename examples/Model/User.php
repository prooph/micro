<?php

/**
 * This file is part of the prooph/micro.
 * (c) 2017-2020 prooph software GmbH <contact@prooph.de>
 * (c) 2017-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\MicroExample\Model\User;

use Generator;
use InvalidArgumentException;
use Phunkie\Types\ImmList;
use Prooph\MicroExample\Model\Command\ChangeUserName;
use Prooph\MicroExample\Model\Command\RegisterUser;
use Prooph\MicroExample\Model\Event\UserNameChanged;
use Prooph\MicroExample\Model\Event\UserRegistered;
use Prooph\MicroExample\Model\Event\UserRegisteredWithDuplicateEmail;
use Prooph\MicroExample\Model\UniqueEmailGuard;

const registerUser = '\Prooph\MicroExample\Model\User\registerUser';

function registerUser(callable $stateResolver, RegisterUser $command, UniqueEmailGuard $guard): Generator
{
    if (! yield $guard->isUnique($command->email())) {
        yield new UserRegisteredWithDuplicateEmail($command->payload());

        return;
    }

    yield new UserRegistered($command->payload());
}

const changeUserName = '\Prooph\MicroExample\Model\User\changeUserName';

function changeUserName(callable $stateResolver, ChangeUserName $command): Generator
{
    if (! \mb_strlen($command->name()) > 3) {
        throw new InvalidArgumentException('Username too short');
    }

    yield new UserNameChanged($command->payload());
}

const apply = '\Prooph\MicroExample\Model\User\apply';

function apply($state, ImmList $events): array
{
    return $events->fold($state, function ($state, $e) {
        switch (\get_class($e)) {
            case UserRegistered::class:
                return \array_merge($state, $e->payload(), ['activated' => true]);
            case UserRegisteredWithDuplicateEmail::class:
                return \array_merge($state, $e->payload(), ['activated' => false, 'blocked_reason' => 'duplicate email']);
            case UserNameChanged::class:
                return \array_merge($state, $e->payload());
        }
    });
}
