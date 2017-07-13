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

namespace Prooph\MicroExample\Script;

use Prooph\Common\Messaging\Message;
use Prooph\Micro\Kernel;
use Prooph\MicroExample\Infrastructure\UserAggregateDefinition;
use Prooph\MicroExample\Model\Command\ChangeUserName;
use Prooph\MicroExample\Model\Command\RegisterUser;
use Prooph\MicroExample\Model\User;

$start = microtime(true);

$autoloader = require __DIR__ . '/../vendor/autoload.php';
$autoloader->addPsr4('Prooph\\MicroExample\\', __DIR__);
require 'Model/User.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$commandMap = [
    RegisterUser::class => [
        'handler' => function (callable $stateResolver, Message $message) use (&$factories): array {
            return User\registerUser($stateResolver, $message, $factories['emailGuard']());
        },
        'definition' => UserAggregateDefinition::class,
    ],
    ChangeUserName::class => [
        'handler' => User\changeUserName,
        'definition' => UserAggregateDefinition::class,
    ],
];

$dispatch = Kernel\buildCommandDispatcher($factories['eventStore']())($factories['snapshotStore']())($commandMap);

$command = new RegisterUser(['id' => '1', 'name' => 'Alex', 'email' => 'member@getprooph.org']);

$dispatch($command);

echo "User was registered\n";

$dispatch(new ChangeUserName(['id' => '1', 'name' => 'Sascha']));

echo "Username changed\n";

$time = microtime(true) - $start;

echo $time . "secs runtime\n\n";
