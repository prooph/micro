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

namespace ProophTest\Micro;

use PHPUnit\Framework\TestCase;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AggregateDefiniton;
use Prooph\Micro\AggregateResult;
use Prooph\Micro\Kernel as f;
use ProophTest\Micro\TestAsset\TestAggregateDefinition;
use Prophecy\Argument;

class KernelTest extends TestCase
{
    /**
     * @test
     */
    public function it_builds_command_dispatcher_and_dispatches(): void
    {
        $commandMap = [
            'some_command' => [
                'handler' => function (array $state, Message $message): AggregateResult {
                    return new AggregateResult([], ['some' => 'state']);
                },
                'definition' => TestAggregateDefinition::class,
            ],
        ];

        $eventStoreFactory = function (): EventStore {
            static $eventStore = null;

            if (null === $eventStore) {
                $eventStore = $this->prophesize(EventStore::class);
                $eventStore->hasStream('foo')->willReturn(true)->shouldBeCalled();
                $eventStore->load(Argument::type(StreamName::class), 1, null, null)->willReturn(new Stream(new StreamName('foo'), new \ArrayIterator()))->shouldBeCalled();
                $eventStore->appendTo(Argument::type(StreamName::class), Argument::type(\Iterator::class))->shouldBeCalled();
                $eventStore = $eventStore->reveal();
            }

            return $eventStore;
        };

        $producerFactory = function () {
            return function (Message $message): void {
            };
        };

        $dispatch = \Prooph\Micro\Kernel\buildCommandDispatcher($eventStoreFactory, $producerFactory, $commandMap);

        $command = $this->prophesize(Message::class);
        $command->messageName()->willReturn('some_command')->shouldBeCalled();

        $result = $dispatch($command->reveal());

        $this->assertEquals(['some' => 'state'], $result);
    }

    /**
     * @test
     */
    public function it_builds_command_dispatcher_and_dispatches_but_breaks_when_handler_returns_invalid_result(): void
    {
        $commandMap = [
            'some_command' => [
                'handler' => function (array $state, Message $message): string {
                    return 'invalid';
                },
                'definition' => TestAggregateDefinition::class,
            ],
        ];

        $eventStoreFactory = function (): EventStore {
            static $eventStore = null;

            if (null === $eventStore) {
                $eventStore = $this->prophesize(EventStore::class);
                $eventStore->hasStream('foo')->willReturn(true)->shouldBeCalled();
                $eventStore->load(Argument::type(StreamName::class), 1, null, null)->willReturn(new Stream(new StreamName('foo'), new \ArrayIterator()))->shouldBeCalled();
                $eventStore = $eventStore->reveal();
            }

            return $eventStore;
        };

        $producerFactory = function (): callable {
            return function (Message $message): void {
            };
        };

        $dispatch = \Prooph\Micro\Kernel\buildCommandDispatcher($eventStoreFactory, $producerFactory, $commandMap);

        $command = $this->prophesize(Message::class);
        $command->messageName()->willReturn('some_command')->shouldBeCalled();

        $result = $dispatch($command->reveal());

        $this->assertInstanceOf(\Exception::class, $result);
        $this->assertEquals('Invalid aggregate result returned', $result->getMessage());
    }

    /**
     * @test
     */
    public function it_loads_empty_state(): void
    {
        $message = $this->prophesize(Message::class);
        $definition = $this->prophesize(AggregateDefiniton::class);

        $result = f\loadState($message->reveal(), $definition->reveal());
        $this->assertInternalType('array', $result);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function it_loads_events_when_stream_not_found(): void
    {
        $factory = function (): EventStore {
            $eventStore = $this->prophesize(EventStore::class);
            $eventStore->hasStream(new StreamName('foo'))->willReturn(false)->shouldBeCalled();

            return $eventStore->reveal();
        };

        $result = f\loadEvents(new StreamName('foo'), null, $factory);

        $this->assertInstanceOf(\ArrayIterator::class, $result);
        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function it_loads_events_when_stream_found(): void
    {
        $factory = function (): EventStore {
            $streamName = new StreamName('foo');
            $eventStore = $this->prophesize(EventStore::class);
            $eventStore->hasStream($streamName)->willReturn(true)->shouldBeCalled();
            $eventStore->load($streamName, 1, null, null)->willReturn(new Stream($streamName, new \ArrayIterator()))->shouldBeCalled();

            return $eventStore->reveal();
        };

        $result = f\loadEvents(new StreamName('foo'), null, $factory);

        $this->assertInstanceOf(\ArrayIterator::class, $result);
        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function it_appends_to_stream_during_persist_when_stream_found(): void
    {
        $factory = function (): EventStore {
            $streamName = new StreamName('foo');
            $eventStore = $this->prophesize(EventStore::class);
            $eventStore->hasStream($streamName)->willReturn(true)->shouldBeCalled();
            $eventStore->appendTo($streamName, Argument::type(\Iterator::class))->shouldBeCalled();

            return $eventStore->reveal();
        };

        $message = $this->prophesize(Message::class);
        $message->messageType()->willReturn(Message::TYPE_EVENT)->shouldBeCalled();
        $raisedEvents = [$message->reveal()];

        $aggregateResult = new AggregateResult($raisedEvents, ['foo' => 'bar']);

        $aggregateDefinition = $this->prophesize(AggregateDefiniton::class);
        $aggregateDefinition->metadataEnricher('some_id')->willReturn(null)->shouldBeCalled();
        $aggregateDefinition->streamName('some_id')->willReturn(new StreamName('foo'))->shouldBeCalled();

        $result = f\persistEvents($aggregateResult, $factory, $aggregateDefinition->reveal(), 'some_id');

        $this->assertInstanceOf(AggregateResult::class, $result);
        $this->assertSame($raisedEvents, $result->raisedEvents());
        $this->assertEquals(['foo' => 'bar'], $result->state());
    }

    /**
     * @test
     */
    public function it_creates_stream_during_persist_when_no_stream_found_and_enriches_with_metadata(): void
    {
        $enrichedMessage = $this->prophesize(Message::class);
        $enrichedMessage->messageType()->willReturn(Message::TYPE_EVENT)->shouldBeCalled();
        $enrichedMessage = $enrichedMessage->reveal();

        $message = $this->prophesize(Message::class);
        $message->messageType()->willReturn(Message::TYPE_EVENT)->shouldBeCalled();
        $message->withAddedMetadata('some', 'metadata')->willReturn($enrichedMessage)->shouldBeCalled();

        $factory = function () use ($enrichedMessage): EventStore {
            $streamName = new StreamName('foo');
            $eventStore = $this->prophesize(EventStore::class);
            $eventStore->hasStream($streamName)->willReturn(false)->shouldBeCalled();
            $eventStore->create(new Stream($streamName, new \ArrayIterator([$enrichedMessage])))->shouldBeCalled();

            return $eventStore->reveal();
        };

        $raisedEvents = [$message->reveal()];

        $aggregateResult = new AggregateResult($raisedEvents, ['foo' => 'bar']);

        $aggregateDefinition = $this->prophesize(AggregateDefiniton::class);
        $aggregateDefinition->metadataEnricher('some_id')->willReturn(
            new class() implements MetadataEnricher {
                public function enrich(Message $message): Message
                {
                    return $message->withAddedMetadata('some', 'metadata');
                }
            }
        )->shouldBeCalled();
        $aggregateDefinition->streamName('some_id')->willReturn(new StreamName('foo'))->shouldBeCalled();

        $result = f\persistEvents($aggregateResult, $factory, $aggregateDefinition->reveal(), 'some_id');

        $this->assertInstanceOf(AggregateResult::class, $result);
        $this->assertSame([$enrichedMessage], $result->raisedEvents());
        $this->assertEquals(['foo' => 'bar'], $result->state());
    }

    /**
     * @test
     */
    public function it_publishes_events(): void
    {
        $message = $this->prophesize(Message::class);
        $message->messageType()->willReturn(Message::TYPE_EVENT)->shouldBeCalled();

        $raisedEvents = [$message->reveal()];

        $aggregateResult = new AggregateResult($raisedEvents, ['foo' => 'bar']);

        $published = false;

        $factory = function () use (&$published) {
            return function (Message $message) use (&$published): void {
                $published = true;
            };
        };

        $result = f\publishEvents($aggregateResult, $factory);

        $this->assertSame($aggregateResult, $result);
    }

    /**
     * @test
     */
    public function it_gets_handler(): void
    {
        $message = $this->prophesize(Message::class);
        $message->messageName()->willReturn('foo')->shouldBeCalled();

        $commandMap = ['foo' => ['handler' => function (): void {
        }]];

        $result = f\getHandler($message->reveal(), $commandMap);

        $this->assertInstanceOf(\Closure::class, $result);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_no_handler_found(): void
    {
        $this->expectException(\RuntimeException::class);

        $message = $this->prophesize(Message::class);
        $message->messageName()->willReturn('unknown')->shouldBeCalled();

        $commandMap = ['foo' => ['handler' => function (): void {
        }]];

        f\getHandler($message->reveal(), $commandMap);
    }

    /**
     * @test
     */
    public function it_gets_aggregate_definition_from_cache(): void
    {
        $message = $this->prophesize(Message::class);
        $message->messageName()->willReturn('foo')->shouldBeCalled();

        $commandMap = ['foo' => ['definition' => TestAggregateDefinition::class]];

        $result = f\getAggregateDefinition($message->reveal(), $commandMap);

        $this->assertInstanceOf(TestAggregateDefinition::class, $result);

        $result2 = f\getAggregateDefinition($message->reveal(), $commandMap);

        $this->assertSame($result, $result2);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_no_definition_found(): void
    {
        $this->expectException(\RuntimeException::class);

        $message = $this->prophesize(Message::class);
        $message->messageName()->willReturn('bar')->shouldBeCalled();

        $commandMap = [];

        f\getAggregateDefinition($message->reveal(), $commandMap);
    }

    /**
     * @test
     */
    public function it_pipes(): void
    {
        $result = f\pipleline('strtolower', 'ucfirst')('aBC');

        $this->assertEquals('Abc', $result);
    }

    /**
     * @test
     */
    public function it_handles_exceptions(): void
    {
        $result = f\pipleline(function () {
            throw new \Exception('Exception there!');
        }, 'ucfirst')('aBC');

        $this->assertInstanceOf(\Exception::class, $result);
        $this->assertEquals('Exception there!', $result->getMessage());
    }
}
