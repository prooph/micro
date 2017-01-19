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

use Prooph\Common\Messaging\Message;
use Prooph\ServiceBus\Async\MessageProducer;
use Prooph\MicroExample\Infrastructure\InMemoryEmailGuard;
use React\Promise\Deferred;

return [
    'eventStore' => function (): \Prooph\EventStore\EventStore {
        static $eventStore = null;

        if (null === $eventStore) {
            $eventStore = new \Prooph\EventStore\InMemoryEventStore();
        }

        return $eventStore;
    },
    'producer' => function () {
        return new class() implements MessageProducer {
            public function __invoke(Message $message, Deferred $deferred = null): void
            {
            }
        };
    },
    'emailGuard' => function (): \Prooph\MicroExample\Model\UniqueEmailGuard {
        static $emailGuard = null;

        if (null === $emailGuard) {
            $emailGuard = new InMemoryEmailGuard();
        }

        return $emailGuard;
    },
];
