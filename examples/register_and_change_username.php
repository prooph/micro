<?php

declare(strict_types = 1);

namespace ProophExample\Micro\Script;

use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Stream;
use Prooph\Micro\AggregateDefiniton;
use Prooph\Micro\AggregateResult;
use Prooph\Micro\Pipe;
use ProophExample\Micro\Infrastructure\UserAggregateDefinition;
use ProophExample\Micro\Model\Command\ChangeUserName;
use ProophExample\Micro\Model\Command\RegisterUser;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Model/User.php';
require __DIR__ . '/../src/Kernel.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$commandMap = [
    RegisterUser::class => [
        'handler' => '\ProophExample\Micro\Model\User\registerUser',
        'definition' => UserAggregateDefinition::class,
    ],
    ChangeUserName::class => [
        'handler' => '\ProophExample\Micro\Model\User\changeUserName',
        'definition' => UserAggregateDefinition::class,
    ]
];

$eventStore = $factories['eventStore'];
$eventBus = $factories['eventBus'];

$dispatch = function(Message $message) use ($commandMap, $eventStore, $eventBus) {
    return \Prooph\Micro\Kernel\dispatch($message, $commandMap, $eventStore(), $eventBus());
};

$command = new RegisterUser(['id' => '1', 'name' => 'Alex', 'email' => 'member@getprooph.org']);

$state = $dispatch($command);

echo "User was registered: \n";
echo json_encode($state) . "\n\n";

$state = $dispatch(new ChangeUserName(['id' => '1', 'name' => 'Sascha']));

echo "Username changed: \n";
echo json_encode($state) . "\n\n";
