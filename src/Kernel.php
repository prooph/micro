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

namespace Prooph\Micro\Kernel;

use ArrayIterator;
use Iterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AggregateDefiniton;
use Prooph\Micro\AggregateResult;
use Prooph\Micro\Pipe;

/**
 * builds a dispatcher to return a function that receives a messages and return the state
 *
 * usage:
 * $dispatch = buildDispatcher($eventStore, $producerFactory, $commandMap);
 * $state = $dispatch($message);
 *
 * $producerFactory is expected to be a callback that returns an instance of Prooph\ServiceBus\Async\MessageProducer.
 * $commandMap is expected to be an array like this:
 * [
 *     RegisterUser::class => [
 *         'handler' => function (array $state, Message $message) use (&$factories): AggregateResult {
 *             return \Prooph\MicroExample\Model\User\registerUser($state, $message, $factories['emailGuard']());
 *         },
 *         'definition' => UserAggregateDefinition::class,
 *     ],
 *     ChangeUserName::class => [
 *         'handler' => '\Prooph\MicroExample\Model\User\changeUserName',
 *         'definition' => UserAggregateDefinition::class,
 *     ],
 * ]
 * $message is expected to be an instance of Prooph\Common\Messaging\Message
 */
function buildCommandDispatcher(callable $eventStoreFactory, callable $producerFactory, array $commandMap): callable
{
    return function (Message $message) use ($eventStoreFactory, $producerFactory, $commandMap) {
        $getDefinition = function (Message $message) use ($commandMap): AggregateDefiniton {
            return getAggregateDefinition($message, $commandMap);
        };

        $loadState = function (AggregateDefiniton $definiton) use ($message): array {
            return loadState($message, $definiton);
        };

        $loadEvents = function (array $state) use ($message, $getDefinition, $eventStoreFactory): Iterator {
            $definition = $getDefinition($message);
            $aggregateId = $definition->extractAggregateId($message);

            return loadEvents(
                $definition->streamName($aggregateId),
                $definition->metadataMatcher($aggregateId),
                $eventStoreFactory
            );
        };

        $reconstituteState = function (Iterator $events) use ($message, $getDefinition) {
            $definition = $getDefinition($message);

            return $definition->reconstituteState($events);
        };

        $handleCommand = function (array $state) use ($message, $commandMap): AggregateResult {
            $handler = getHandler($message, $commandMap);

            $aggregateResult = $handler($state, $message);

            if (! $aggregateResult instanceof AggregateResult) {
                throw new \RuntimeException('Invalid aggregate result returned');
            }

            return $aggregateResult;
        };

        $persistEvents = function (AggregateResult $aggregateResult) use ($eventStoreFactory, $message, $getDefinition): AggregateResult {
            $definition = $getDefinition($message);

            return persistEvents($aggregateResult, $eventStoreFactory, $definition, $definition->extractAggregateId($message));
        };

        $publishEvents = function (AggregateResult $aggregateResult) use ($producerFactory) {
            return publishEvents($aggregateResult, $producerFactory);
        };

        return (new Pipe($message))
            ->pipe($getDefinition)
            ->pipe($loadState)
            ->pipe($loadEvents)
            ->pipe($reconstituteState)
            ->pipe($handleCommand)
            ->pipe($persistEvents)
            ->pipe($publishEvents)
            ->result();
    };
}

function loadState(Message $message, AggregateDefiniton $definiton): array
{
    return []; // @todo: fetch from projections
}

function loadEvents(
    StreamName $streamName,
    ?MetadataMatcher $metadataMatcher,
    callable $eventStoreFactory,
    int $fromVersion = 1,
    array $state = []
): Iterator {
    $eventStore = $eventStoreFactory();

    if ($eventStore->hasStream($streamName)) {
        return $eventStore->load($streamName, $fromVersion, null, $metadataMatcher)->streamEvents();
    }

    return new ArrayIterator();
}

function persistEvents(AggregateResult $aggregateResult, callable $eventStoreFactory, AggregateDefiniton $definition, string $aggregateId): AggregateResult
{
    $events = $aggregateResult->raisedEvents();

    if ($metadataEnricher = $definition->metadataEnricher($aggregateId)) {
        $events = array_map([$metadataEnricher, 'enrich'], $events);
    }

    $streamName = $definition->streamName($aggregateId);

    $eventStore = $eventStoreFactory();

    if ($eventStore->hasStream($streamName)) {
        $eventStore->appendTo($streamName, new \ArrayIterator($events));
    } else {
        $eventStore->create(new Stream($streamName, new \ArrayIterator($events)));
    }

    return new AggregateResult($events, $aggregateResult->state());
}

function publishEvents(AggregateResult $aggregateResult, callable $producerCallback): array
{
    $producer = null;
    foreach ($aggregateResult->raisedEvents() as $event) {
        if (null === $producer) {
            $producer = $producerCallback();
        }
        $producer($event);
    }

    return $aggregateResult->state();
}

function getHandler(Message $message, array $commandMap): callable
{
    if (! array_key_exists($message->messageName(), $commandMap)) {
        throw new \RuntimeException(sprintf('Unknown message %s. Message name not mapped to an aggregate.', $message->messageName()));
    }

    return $commandMap[$message->messageName()]['handler'];
}

function getAggregateDefinition(Message $message, array $commandMap): AggregateDefiniton
{
    static $cached = [];

    $messageName = $message->messageName();

    if (isset($cached[$messageName])) {
        return $cached[$messageName];
    }

    if (! isset($commandMap[$messageName])) {
        throw new \RuntimeException(sprintf('Unknown message %s. Message name not mapped to an aggregate.', $message->messageName()));
    }

    $cached[$messageName] = new $commandMap[$messageName]['definition']();

    return $cached[$messageName];
}
