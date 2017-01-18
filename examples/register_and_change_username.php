<?php

declare(strict_types = 1);

namespace ProophExample\Micro\Script;

use Prooph\Common\Messaging\Message;
use Prooph\Micro\AggregateResult;
use ProophExample\Micro\Infrastructure\UserAggregateDefinition;
use ProophExample\Micro\Model\Command\ChangeUserName;
use ProophExample\Micro\Model\Command\RegisterUser;

require __DIR__ . '/../vendor/autoload.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$commandMap = [
    RegisterUser::class => [
        'handler' => function(Message $message, array $state) use (&$factories): AggregateResult {
            $handler = '\ProophExample\Micro\Model\User\registerUser';
            return $handler($message, $state, $factories['emailGuard']());
        },
        'definition' => UserAggregateDefinition::class,
    ],
    ChangeUserName::class => [
        'handler' => '\ProophExample\Micro\Model\User\changeUserName',
        'definition' => UserAggregateDefinition::class,
    ]
];

$eventStore = $factories['eventStore'];
$producer = $factories['messageProducer'];

$dispatch = function(Message $message) use ($commandMap, $eventStore, $producer) {
    return \Prooph\Micro\Kernel\dispatch($message, $commandMap, $eventStore(), $producer);
};

$command = new RegisterUser(['id' => '1', 'name' => 'Alex', 'email' => 'member@getprooph.org']);

$state = $dispatch($command);

echo "User was registered: \n";
echo json_encode($state) . "\n\n";

$state = $dispatch(new ChangeUserName(['id' => '1', 'name' => 'Sascha']));

echo "Username changed: \n";
echo json_encode($state) . "\n\n";
