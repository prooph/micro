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
use Prooph\EventStore\EventStore;
use Prooph\Micro\SnapshotReadModel;
use Prooph\MicroExample\Infrastructure\UserAggregateDefinition;

$autoloader = require __DIR__ . '/../vendor/autoload.php';
$autoloader->addPsr4('Prooph\\MicroExample\\', __DIR__);
require 'Model/User.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$eventStore = $factories['eventStore']();

/* @var EventStore $eventStore */

$readModel = new SnapshotReadModel(
    $factories['snapshotStore'](),
    new UserAggregateDefinition()
);

$projection = $eventStore->createReadModelProjection(
    'user_snapshots',
    $readModel
);

$projection
    ->fromStream('user_stream')
    ->whenAny(function ($state, Message $event): void {
        $this->readModel()->stack('replay', $event);
    })
    ->run();
