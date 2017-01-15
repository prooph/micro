<?php

declare(strict_types = 1);

namespace ProophExample\Micro\Script;

use ProophExample\Micro\Model\Command\ChangeUserName;
use ProophExample\Micro\Model\Command\RegisterUser;
use ProophExample\Micro\Model\User;

require __DIR__ . '/../vendor/autoload.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$commandAggregateMap = [
    //Example with dependency injection
    RegisterUser::class => [
        User::class,
        function(RegisterUser $command, array $state) use (&$factories) {
            return User::register($command, $state, $factories['emailGuard']());
        }
    ],
    //Example where we don't need to inject additional services
    ChangeUserName::class => [
        User::class,
        'changeUserName'
    ]

];

$eventStore = $factories['eventStore']();

$dispatch = \Prooph\Micro\Fn::connect($commandAggregateMap, $eventStore);

$userState = $dispatch(new RegisterUser(['id' => '1', 'name' => 'Alex', 'email' => 'member@getprooph.org']));

echo "User was registered: \n";
echo json_encode($userState) . "\n\n";

$userState = $dispatch(new ChangeUserName(['id' => '1', 'name' => 'Sascha']));

echo "Username changed: \n";
echo json_encode($userState) . "\n\n";

