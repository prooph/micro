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
use Prooph\MicroExample\Infrastructure\InMemoryEmailGuard;

$factories = [
    'eventStore' => function (): \Prooph\EventStore\EventStore {
        static $eventStore = null;

        if (null === $eventStore) {
            $eventStore = new \Prooph\EventStore\InMemoryEventStore();
        }

        return $eventStore;
    },
    'producer' => function (): callable {
        return function (Message $message): void {
        };
    },
    'amqpChannel' => function (): \AMQPChannel {
        static $channel = null;

        if (null === $channel) {
            $connection = new \AMQPConnection();
            $connection->connect();

            $channel = new \AMQPChannel($connection);
        }

        return $channel;
    },
    'emailGuard' => function (): \Prooph\MicroExample\Model\UniqueEmailGuard {
        static $emailGuard = null;

        if (null === $emailGuard) {
            $emailGuard = new InMemoryEmailGuard();
        }

        return $emailGuard;
    },
];

$factories['amqpProducer'] = function () use ($factories): callable {
    return \Prooph\Micro\AmqpPublisher\buildPublisher(
        $factories['amqpChannel'](),
        new \Prooph\Common\Messaging\NoOpMessageConverter(),
        'micro'
    );
};

$factories['startAmqpTransaction'] = function () use ($factories): callable {
    return function () use ($factories): void {
        $channel = $factories['amqpChannel']();
        $channel->startTransaction();
    };
};

$factories['commitAmqpTransaction'] = function () use ($factories): callable {
    return function () use ($factories): void {
        $channel = $factories['amqpChannel']();
        $result = $channel->commitTransaction();

        if (false === $result) {
            \Prooph\Micro\AmqpPublisher\throwCommitFailed();
        }
    };
};

return $factories;
