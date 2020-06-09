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

namespace Prooph\MicroExample\Script;

use Amp\Loop;
use const PHP_EOL;
use Phunkie\Types\ImmList;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\EventStoreConnectionFactory;
use Prooph\Micro\Kernel;
use Prooph\MicroExample\Infrastructure\InMemoryEmailGuard;
use Prooph\MicroExample\Infrastructure\UserSpecification;
use Prooph\MicroExample\Model\Command\ChangeUserName;
use Prooph\MicroExample\Model\Command\RegisterUser;
use Prooph\MicroExample\Model\Command\UnknownCommand;
use Prooph\MicroExample\Model\User;
use Throwable;

$autoloader = require __DIR__ . '/../vendor/autoload.php';
$autoloader->addPsr4('Prooph\\MicroExample\\', __DIR__);
require 'Model/User.php';

function showResult($result): void
{
    if ($result instanceof ImmList) {
        echo $result->show() . PHP_EOL;
        echo \json_encode($result->head()->payload()) . PHP_EOL . PHP_EOL;
    }
}

Loop::run(function (): \Generator {
    $start = \microtime(true);

    $settings = ConnectionSettings::create()
        ->setDefaultUserCredentials(
            new UserCredentials('admin', 'changeit')
        );

    $connection = EventStoreConnectionFactory::createFromConnectionString(
        'ConnectTo=tcp://admin:changeit@10.121.1.4:1113',
        $settings->build()
    );

    $connection->onConnected(function () {
        echo 'Event Store connection established' . PHP_EOL;
    });

    $connection->onClosed(function () {
        echo 'Event Store connection closed' . PHP_EOL;
    });

    yield $connection->connectAsync();

    $uniqueEmailGuard = new InMemoryEmailGuard();

    $commandMap = ImmMap([
        ChangeUserName::class => fn ($m) => new UserSpecification($m, User\changeUserName),
        RegisterUser::class => fn ($m) => new UserSpecification($m, fn (callable $s, $m) => User\registerUser($s, $m, $uniqueEmailGuard)),
    ]);

    $dispatch = Kernel\buildCommandDispatcher($connection, $commandMap);

    $userId = Guid::generateString();

    try {
        $result = yield $dispatch(new RegisterUser(['id' => $userId, 'name' => 'Alex', 'email' => 'member@getprooph.org']));
    } catch (Throwable $e) {
        echo \get_class($e) . ': ' . $e->getMessage() . PHP_EOL . PHP_EOL;
    }

    showResult($result);

    try {
        $result = yield $dispatch(new ChangeUserName(['id' => $userId, 'name' => 'Sascha']));
    } catch (Throwable $e) {
        echo \get_class($e) . ': ' . $e->getMessage() . PHP_EOL . PHP_EOL;
    }

    showResult($result);

    try {
        $result = yield $dispatch(new UnknownCommand());
    } catch (Throwable $e) {
        echo \get_class($e) . ': ' . $e->getMessage() . PHP_EOL . PHP_EOL;
    }

    showResult($result);

    $time = \microtime(true) - $start;

    echo $time . "secs runtime\n\n";

    $connection->close();
});
