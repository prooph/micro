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
use Prooph\Micro\AggregateResult;
use Prooph\MicroExample\Infrastructure\UserAggregateDefinition;
use Prooph\MicroExample\Model\Command\ChangeUserName;
use Prooph\MicroExample\Model\Command\InvalidCommand;
use Prooph\MicroExample\Model\Command\RegisterUser;
use Prooph\MicroExample\Model\Command\UnknownCommand;

$start = microtime(true);

$autoloader = require __DIR__ . '/../vendor/autoload.php';
$autoloader->addPsr4('Prooph\\MicroExample\\', __DIR__);
require 'Model/User.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$commandMap = [
    RegisterUser::class => [
        'handler' => function (array $state, Message $message) use (&$factories): AggregateResult {
            return \Prooph\MicroExample\Model\User\registerUser($state, $message, $factories['emailGuard']());
        },
        'definition' => UserAggregateDefinition::class,
    ],
    ChangeUserName::class => [
        'handler' => '\Prooph\MicroExample\Model\User\changeUserName',
        'definition' => UserAggregateDefinition::class,
    ],
];

$dispatch = \Prooph\Micro\Kernel\buildCommandDispatcher($factories['eventStore'], $factories['producer'], $commandMap);

$command = new RegisterUser(['id' => '1', 'name' => 'Alex', 'email' => 'member@getprooph.org']);

$state = $dispatch($command);

echo "User was registered: \n";
echo json_encode($state) . "\n\n";

$state = $dispatch(new ChangeUserName(['id' => '1', 'name' => 'Sascha']));

echo "Username changed: \n";
echo json_encode($state) . "\n\n";

$state = $dispatch(new InvalidCommand());

echo get_class($state) . "\n";
echo json_encode($state->getMessage()) . "\n\n";

$state = $dispatch(new UnknownCommand());

echo get_class($state) . "\n";
echo json_encode($state->getMessage()) . "\n\n";

$time = microtime(true) - $start;

echo $time . "secs runtime\n\n";
