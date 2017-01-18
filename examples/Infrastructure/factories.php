<?php

declare(strict_types = 1);

use ProophExample\Micro\Infrastructure\InMemoryEmailGuard;

$factories = [
    'eventStore' => function() {
        return new \Prooph\EventStore\InMemoryEventStore();
    },
    'emailGuard' => new class() {

        private static $emailGuard;

        public function __invoke()
        {
            if(null === self::$emailGuard) {
                self::$emailGuard = new InMemoryEmailGuard();
            }

            return self::$emailGuard;
        }
    },
    'eventBus' => function() {
        return new \Prooph\ServiceBus\EventBus();
    },
];

return $factories;


