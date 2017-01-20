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

namespace Prooph\Micro\AmqpPublisher;

use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageConverter;
use Prooph\Common\Messaging\MessageDataAssertion;

const buildPublisher = 'Prooph\Micro\AmqpProducer\buildPublisher';

function buildPublisher(\AMQPChannel $channel, MessageConverter $messageConverter, string $exchangeName): callable
{
    if (! $channel->isConnected()) {
        throw new \RuntimeException('Provided AMQP channel is not connected');
    }

    $exchange = new \AMQPExchange($channel);
    $exchange->setName($exchangeName);

    return function (Message $message) use ($messageConverter, $exchange) {
        $messageData = $messageConverter->convertToArray($message);
        MessageDataAssertion::assert($messageData);
        $messageData['created_at'] = $message->createdAt()->format('Y-m-d\TH:i:s.u');

        $exchange->publish(json_encode($messageData), $message->messageName(), \AMQP_NOPARAM, [
            'timestamp' => $message->createdAt()->getTimestamp(),
            'type' => $message->messageType(),
        ]);
    };
}

const throwCommitFailed = 'Prooph\Micro\AmqpPublisher\throwCommitFailed';

function throwCommitFailed(): void
{
    throw new \RuntimeException('AMQP transaction failed');
}
