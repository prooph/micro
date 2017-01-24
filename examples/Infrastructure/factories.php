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

use Prooph\EventStore\EventStore;
use Prooph\EventStore\InMemoryEventStore;
use Prooph\MicroExample\Infrastructure\InMemoryEmailGuard;
use Prooph\MicroExample\Model\UniqueEmailGuard;
use Prooph\SnapshotStore\InMemorySnapshotStore;
use Prooph\SnapshotStore\SnapshotStore;

$factories = [
    'eventStore' => function (): EventStore {
        static $eventStore = null;

        if (null === $eventStore) {
            $eventStore = new InMemoryEventStore();
        }

        return $eventStore;
    },
    'snapshotStore' => function (): SnapshotStore {
        static $snapshotStore = null;

        if (null === $snapshotStore) {
            $snapshotStore = new InMemorySnapshotStore();
        }

        return $snapshotStore;
    },
    'emailGuard' => function (): UniqueEmailGuard {
        static $emailGuard = null;

        if (null === $emailGuard) {
            $emailGuard = new InMemoryEmailGuard();
        }

        return $emailGuard;
    },
];

return $factories;
