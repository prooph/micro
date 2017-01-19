<?php

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
