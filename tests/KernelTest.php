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
use Prooph\Micro\Kernel as f;
use Prooph\SnapshotStore\InMemorySnapshotStore;
use Prooph\SnapshotStore\Snapshot;
use Prooph\SnapshotStore\SnapshotStore;
use ProophTest\Micro\TestAsset\OneStreamPerAggregateTestAggregateDefinition;
use ProophTest\Micro\TestAsset\SingleStreamTestAggregateDefinition;
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
                'handler' => function (callable $stateResolver, Message $message): array {
                    return $stateResolver();
                },
                'definition' => SingleStreamTestAggregateDefinition::class,
            ],
        ];

        $eventStoreFactory = function (): EventStore {
            static $eventStore = null;

            if (null === $eventStore) {
                $eventStore = $this->prophesize(EventStore::class);
                $eventStore->hasStream('foo')->willReturn(true)->shouldBeCalled();
                $eventStore->load(Argument::type(StreamName::class), 1, null, null)->willReturn(new \ArrayIterator())->shouldBeCalled();
                $eventStore->appendTo(Argument::type(StreamName::class), Argument::type(\Iterator::class))->shouldBeCalled();
                $eventStore = $eventStore->reveal();
            }

            return $eventStore;
        };

        $snapshotStoreFactory = function (): SnapshotStore {
            static $snapshotStore = null;

            if (null === $snapshotStore) {
                $snapshotStore = new InMemorySnapshotStore();
            }

            return $snapshotStore;
        };

        $dispatch = \Prooph\Micro\Kernel\buildCommandDispatcher(
            $commandMap,
            $eventStoreFactory,
            $snapshotStoreFactory
        );

        $command = $this->prophesize(Message::class);
        $command->messageName()->willReturn('some_command')->shouldBeCalled();

        $events = $dispatch($command->reveal());
        $this->assertEmpty($events);
    }

    /**
     * @test
     */
    public function it_does_not_load_events_when_state_is_not_resolved(): void
    {
        $commandMap = [
            'some_command' => [
                'handler' => function (callable $stateResolver, Message $message): array {
                    return [];
                },
                'definition' => SingleStreamTestAggregateDefinition::class,
            ],
        ];

        $eventStoreFactory = function (): EventStore {
            static $eventStore = null;

            if (null === $eventStore) {
                $eventStore = $this->prophesize(EventStore::class);
                $eventStore->hasStream('foo')->willReturn(true)->shouldBeCalled();
                $eventStore->load(Argument::type(StreamName::class), 1, null, null)->willReturn(new \ArrayIterator())->shouldNotBeCalled();
                $eventStore->appendTo(Argument::type(StreamName::class), Argument::type(\Iterator::class))->shouldBeCalled();
                $eventStore = $eventStore->reveal();
            }

            return $eventStore;
        };

        $snapshotStoreFactory = function (): SnapshotStore {
            static $snapshotStore = null;

            if (null === $snapshotStore) {
                $snapshotStore = new InMemorySnapshotStore();
            }

            return $snapshotStore;
        };

        $dispatch = \Prooph\Micro\Kernel\buildCommandDispatcher(
            $commandMap,
            $eventStoreFactory,
            $snapshotStoreFactory
        );

        $command = $this->prophesize(Message::class);
        $command->messageName()->willReturn('some_command')->shouldBeCalled();

        $events = $dispatch($command->reveal());
        $this->assertEmpty($events);
    }

    /**
     * @test
     */
    public function it_builds_command_dispatcher_and_dispatches_but_breaks_when_handler_returns_invalid_result(): void
    {
        $commandMap = [
            'some_command' => [
                'handler' => function (callable $stateResolver, Message $message): string {
                    return 'invalid';
                },
                'definition' => SingleStreamTestAggregateDefinition::class,
            ],
        ];

        $eventStoreFactory = function (): EventStore {
            static $eventStore = null;

            if (null === $eventStore) {
                $eventStore = $this->prophesize(EventStore::class);
                $eventStore->hasStream('foo')->willReturn(true)->shouldBeCalled();
                $eventStore->load(Argument::type(StreamName::class), 1, null, null)->willReturn(new \ArrayIterator())->shouldBeCalled();
                $eventStore = $eventStore->reveal();
            }

            return $eventStore;
        };

        $snapshotStoreFactory = function (): SnapshotStore {
            static $snapshotStore = null;

            if (null === $snapshotStore) {
                $snapshotStore = new InMemorySnapshotStore();
            }

            return $snapshotStore;
        };

        $dispatch = \Prooph\Micro\Kernel\buildCommandDispatcher(
            $commandMap,
            $eventStoreFactory,
            $snapshotStoreFactory
        );

        $command = $this->prophesize(Message::class);
        $command->messageName()->willReturn('some_command')->shouldBeCalled();

        $result = $dispatch($command->reveal());

        $this->assertInstanceOf(\RuntimeException::class, $result);
        $this->assertEquals('The handler did not return an array', $result->getMessage());
    }

    /**
     * @test
     */
    public function it_loads_state_from_empty_snapshot_store(): void
    {
        $snapshotStore = $this->prophesize(SnapshotStore::class);

        $message = $this->prophesize(Message::class);
        $message = $message->reveal();

        $definition = $this->prophesize(AggregateDefiniton::class);
        $definition->aggregateType()->willReturn('test')->shouldBeCalled();
        $definition->extractAggregateId($message)->willReturn('42')->shouldBeCalled();

        $result = f\loadState($snapshotStore->reveal(), $message, $definition->reveal());
        $this->assertInternalType('array', $result);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function it_loads_state_from_snapshot_store(): void
    {
        $snapshotStore = new InMemorySnapshotStore();
        $snapshotStore->save(new Snapshot(
            'test',
            '42',
            ['foo' => 'bar'],
            1,
            new \DateTimeImmutable()
        ));

        $message = $this->prophesize(Message::class);
        $message = $message->reveal();

        $definition = $this->prophesize(AggregateDefiniton::class);
        $definition->aggregateType()->willReturn('test')->shouldBeCalled();
        $definition->extractAggregateId($message)->willReturn('42')->shouldBeCalled();

        $result = f\loadState($snapshotStore, $message, $definition->reveal());
        $this->assertEquals(['foo' => 'bar'], $result);
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

        $result = f\loadEvents(new SingleStreamTestAggregateDefinition(), 'foo', 1, $factory);

        $this->assertInstanceOf(\EmptyIterator::class, $result);
    }

    /**
     * @test
     */
    public function it_loads_throws_exception_when_loading_events_with_invalid_event_store_factory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('$eventStoreFactory did not return an instance of ' . EventStore::class);

        $factory = function (): void {
        };

        f\loadEvents(new SingleStreamTestAggregateDefinition(), 'foo', 1, $factory);
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
            $eventStore->load($streamName, 1, null, null)->willReturn(new \ArrayIterator())->shouldBeCalled();

            return $eventStore->reveal();
        };

        $result = f\loadEvents(new SingleStreamTestAggregateDefinition(), 'foo', 1, $factory);

        $this->assertInstanceOf(\ArrayIterator::class, $result);
        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function it_loads_events_when_stream_found_using_one_stream_per_aggregate(): void
    {
        $factory = function (): EventStore {
            $eventStore = $this->prophesize(EventStore::class);
            $eventStore->hasStream(Argument::type(StreamName::class))->willReturn(true)->shouldBeCalled();
            $eventStore->load(Argument::type(StreamName::class), 1, null, null)->willReturn(new \ArrayIterator())->shouldBeCalled();

            return $eventStore->reveal();
        };

        $result = f\loadEvents(new OneStreamPerAggregateTestAggregateDefinition(), 'foo', 1, $factory);

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
        $raisedEvents = [$message->reveal()];

        $aggregateDefinition = $this->prophesize(AggregateDefiniton::class);
        $aggregateDefinition->extractAggregateVersion($message)->willReturn(42)->shouldBeCalled();
        $aggregateDefinition->metadataEnricher('some_id', 42)->willReturn(null)->shouldBeCalled();
        $aggregateDefinition->streamName()->willReturn(new StreamName('foo'))->shouldBeCalled();
        $aggregateDefinition->hasOneStreamPerAggregate()->willReturn(false)->shouldBeCalled();

        $result = f\persistEvents($raisedEvents, $factory, $aggregateDefinition->reveal(), 'some_id');

        $this->assertSame($raisedEvents, $result);
    }

    /**
     * @test
     */
    public function it_appends_to_stream_during_persist_when_stream_found_using_one_stream_per_aggregate(): void
    {
        $factory = function (): EventStore {
            $eventStore = $this->prophesize(EventStore::class);
            $eventStore->hasStream(Argument::type(StreamName::class))->willReturn(true)->shouldBeCalled();
            $eventStore->appendTo(Argument::type(StreamName::class), Argument::type(\Iterator::class))->shouldBeCalled();

            return $eventStore->reveal();
        };

        $message = $this->prophesize(Message::class);
        $raisedEvents = [$message->reveal()];

        $aggregateDefinition = $this->prophesize(AggregateDefiniton::class);
        $aggregateDefinition->extractAggregateVersion($message)->willReturn(42)->shouldBeCalled();
        $aggregateDefinition->metadataEnricher('some_id', 42)->willReturn(null)->shouldBeCalled();
        $aggregateDefinition->streamName()->willReturn(new StreamName('foo'))->shouldBeCalled();
        $aggregateDefinition->hasOneStreamPerAggregate()->willReturn(true)->shouldBeCalled();

        $result = f\persistEvents($raisedEvents, $factory, $aggregateDefinition->reveal(), 'some_id');

        $this->assertSame($raisedEvents, $result);
    }

    /**
     * @test
     */
    public function it_throws_exception_during_append_if_invalid_event_store_factory_given(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('$eventStoreFactory did not return an instance of ' . EventStore::class);

        $factory = function (): void {
        };

        $message = $this->prophesize(Message::class);
        $raisedEvents = [$message->reveal()];

        $aggregateDefinition = $this->prophesize(AggregateDefiniton::class);

        f\persistEvents($raisedEvents, $factory, $aggregateDefinition->reveal(), 'some_id');
    }

    /**
     * @test
     */
    public function it_creates_stream_during_persist_when_no_stream_found_and_enriches_with_metadata(): void
    {
        $enrichedMessage = $this->prophesize(Message::class);
        $enrichedMessage = $enrichedMessage->reveal();

        $message = $this->prophesize(Message::class);
        $message->withAddedMetadata('some', 'metadata')->willReturn($enrichedMessage)->shouldBeCalled();

        $factory = function () use ($enrichedMessage): EventStore {
            $streamName = new StreamName('foo');
            $eventStore = $this->prophesize(EventStore::class);
            $eventStore->hasStream($streamName)->willReturn(false)->shouldBeCalled();
            $eventStore->create(new Stream($streamName, new \ArrayIterator([$enrichedMessage])))->shouldBeCalled();

            return $eventStore->reveal();
        };

        $aggregateDefinition = $this->prophesize(AggregateDefiniton::class);
        $aggregateDefinition->extractAggregateVersion($message)->willReturn(42)->shouldBeCalled();
        $aggregateDefinition->metadataEnricher('some_id', 42)->willReturn(
            new class() implements MetadataEnricher {
                public function enrich(Message $message): Message
                {
                    return $message->withAddedMetadata('some', 'metadata');
                }
            }
        )->shouldBeCalled();
        $aggregateDefinition->streamName()->willReturn(new StreamName('foo'))->shouldBeCalled();
        $aggregateDefinition->hasOneStreamPerAggregate()->willReturn(false)->shouldBeCalled();

        $events = f\persistEvents([$message->reveal()], $factory, $aggregateDefinition->reveal(), 'some_id');

        $this->assertSame([$enrichedMessage], $events);
    }

    /**
     * @test
     */
    public function it_gets_handler(): void
    {
        $message = $this->prophesize(Message::class);
        $message->messageName()->willReturn('foo')->shouldBeCalled();

        $commandMap = [
            'foo' => [
                'handler' => function (): void {
                },
                'definition' => SingleStreamTestAggregateDefinition::class,
            ],
        ];

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

        $commandMap = ['foo' => ['definition' => SingleStreamTestAggregateDefinition::class]];

        $result = f\getAggregateDefinition($message->reveal(), $commandMap);

        $this->assertInstanceOf(SingleStreamTestAggregateDefinition::class, $result);

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
        $result = f\pipeline('strtolower', 'ucfirst')('aBC');

        $this->assertEquals('Abc', $result);
    }

    /**
     * @test
     */
    public function it_handles_exceptions(): void
    {
        $result = f\pipeline(function () {
            throw new \Exception('Exception there!');
        }, 'ucfirst')('aBC');

        $this->assertInstanceOf(\Exception::class, $result);
        $this->assertEquals('Exception there!', $result->getMessage());
    }
}
