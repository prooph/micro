<?php

declare(strict_types=1);

namespace ProophExample\Micro\Script;

use Prooph\Common\Messaging\Message;
use Prooph\Micro\AggregateResult;
use ProophExample\Micro\Infrastructure\UserAggregateDefinition;
use ProophExample\Micro\Model\Command\ChangeUserName;
use ProophExample\Micro\Model\Command\InvalidCommand;
use ProophExample\Micro\Model\Command\RegisterUser;
use ProophExample\Micro\Model\Command\UnknownCommand;

$start = microtime(true);

require __DIR__ . '/../vendor/autoload.php';
require 'Model/User.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$commandMap = [
    RegisterUser::class => [
        'handler' => function (array $state, Message $message) use (&$factories): AggregateResult {
            return \ProophExample\Micro\Model\User\registerUser($state, $message, $factories['emailGuard']());
        },
        'definition' => UserAggregateDefinition::class,
    ],
    ChangeUserName::class => [
        'handler' => '\ProophExample\Micro\Model\User\changeUserName',
        'definition' => UserAggregateDefinition::class,
    ],
];

$dispatch = \Prooph\Micro\Kernel\buildCommandDispatcher($factories['eventStore'], $factories['producer'], $commandMap);

$command = new RegisterUser(['id' => '1', 'name' => 'Alex', 'email' => 'member@getprooph.org']);

$state = $dispatch($command);

echo get_class($state) . "\n";
echo "User was registered: \n";
echo json_encode($state()) . "\n\n";

$state = $dispatch(new ChangeUserName(['id' => '1', 'name' => 'Sascha']));

echo get_class($state) . "\n";
echo "Username changed: \n";
echo json_encode($state()) . "\n\n";

$state = $dispatch(new InvalidCommand());

echo get_class($state) . "\n";
echo json_encode($state()) . "\n\n";

$state = $dispatch(new UnknownCommand());

echo get_class($state) . "\n";
echo json_encode($state()) . "\n\n";

$time = microtime(true) - $start;

echo $time . "secs runtime\n\n";
