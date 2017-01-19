<?php

declare(strict_types=1);

use Prooph\Common\Messaging\Message;
use Prooph\ServiceBus\Async\MessageProducer;
use ProophExample\Micro\Infrastructure\InMemoryEmailGuard;
use React\Promise\Deferred;

$factories = [
    'eventStore' => function () {
        return new \Prooph\EventStore\InMemoryEventStore();
    },
    'emailGuard' => new class() {
        private static $emailGuard;

        public function __invoke()
        {
            if (null === self::$emailGuard) {
                self::$emailGuard = new InMemoryEmailGuard();
            }

            return self::$emailGuard;
        }
    },
    'messageProducer' => new class() implements MessageProducer {
        public function __invoke(Message $message, Deferred $deferred = null): void
        {
        }
    },
];

return $factories;
