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
use Prooph\Micro\Kernel;
use Prooph\MicroExample\Infrastructure\UserAggregateDefinition;
use Prooph\MicroExample\Model\Command\ChangeUserName;
use Prooph\MicroExample\Model\Command\InvalidCommand;
use Prooph\MicroExample\Model\Command\RegisterUser;
use Prooph\MicroExample\Model\Command\UnknownCommand;
use Prooph\MicroExample\Model\User;

$start = microtime(true);

$autoloader = require __DIR__ . '/../vendor/autoload.php';
$autoloader->addPsr4('Prooph\\MicroExample\\', __DIR__);
require 'Model/User.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$commandMap = [
    RegisterUser::class => [
        'handler' => function (array $state, Message $message) use (&$factories): AggregateResult {
            return User\registerUser($state, $message, $factories['emailGuard']());
        },
        'definition' => UserAggregateDefinition::class,
    ],
    ChangeUserName::class => [
        'handler' => User\changeUserName,
        'definition' => UserAggregateDefinition::class,
    ],
];

$dispatch = Kernel\buildCommandDispatcher(
    $factories['eventStore'],
    $factories['snapshotStore'],
    $commandMap,
    $factories['producer']
);

// uncomment to enable amqp publisher
//$dispatch = Kernel\buildCommandDispatcher(
//    $factories['eventStore'],
//    $commandMap,
//    $factories['amqpProducer'],
//    $factories['startAmqpTransaction'],
//    $factories['commitAmqpTransaction']
//);

$command = new RegisterUser(['id' => '1', 'name' => 'Alex', 'email' => 'member@getprooph.org']);

$state = $dispatch($command);

echo "User was registered: \n";
echo json_encode($state) . "\n\n";

$state = $dispatch(new ChangeUserName(['id' => '1', 'name' => 'Sascha']));

echo "Username changed: \n";
echo json_encode($state) . "\n\n";

// should return a TypeError
$state = $dispatch(new InvalidCommand());

echo get_class($state) . "\n";
echo json_encode($state->getMessage()) . "\n\n";

$state = $dispatch(new UnknownCommand());

// should return a RuntimeException
echo get_class($state) . "\n";
echo json_encode($state->getMessage()) . "\n\n";

$time = microtime(true) - $start;

echo $time . "secs runtime\n\n";
