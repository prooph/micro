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
use Phunkie\Validation\Failure;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\InMemoryEventStore;
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalEventStore;
use Prooph\Micro\AggregateDefinition;
use Prooph\Micro\Kernel as k;
use Prooph\SnapshotStore\InMemorySnapshotStore;
use Prooph\SnapshotStore\Snapshot;
use Prooph\SnapshotStore\SnapshotStore;
use ProophTest\Micro\TestAsset\OneStreamPerAggregateTestAggregateDefinition;
use ProophTest\Micro\TestAsset\SingleStreamTestAggregateDefinition;
use ProophTest\Micro\TestAsset\TestDomainEvent;
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
                    $stateResolver();

                    return [new TestDomainEvent(['foo' => 'bar'])];
                },
                'definition' => SingleStreamTestAggregateDefinition::class,
            ],
        ];

        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->hasStream('foo')->willReturn(true)->shouldBeCalled();
        $eventStore->load(Argument::type(StreamName::class), 1, null, null)->willReturn(new \ArrayIterator())->shouldBeCalled();
        $eventStore->appendTo(Argument::type(StreamName::class), Argument::type(\Iterator::class))->shouldBeCalled();

        $dispatch = \Prooph\Micro\Kernel\buildCommandDispatcher($eventStore->reveal(), $commandMap, new InMemorySnapshotStore());

        $command = $this->prophesize(Message::class);
        $command->messageName()->willReturn('some_command')->shouldBeCalled();

        $validation = $dispatch($command->reveal());

        if ($validation instanceof Failure) {
            $this->fail($validation->toString());
        }
    }

    /**
     * @test
     */
    public function it_does_not_load_events_when_state_is_not_resolved(): void
    {
        $commandMap = [
            'some_command' => [
                'handler' => function (callable $stateResolver, Message $message): array {
                    return [new TestDomainEvent(['foo' => 'bar'])];
                },
                'definition' => SingleStreamTestAggregateDefinition::class,
            ],
        ];

        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->hasStream('foo')->willReturn(true)->shouldBeCalled();
        $eventStore->load(Argument::type(StreamName::class), 1, null, null)->willReturn(new \ArrayIterator())->shouldNotBeCalled();
        $eventStore->appendTo(Argument::type(StreamName::class), Argument::type(\Iterator::class))->shouldBeCalled();

        $dispatch = \Prooph\Micro\Kernel\buildCommandDispatcher($eventStore->reveal(), $commandMap, new InMemorySnapshotStore());

        $command = $this->prophesize(Message::class);
        $command->messageName()->willReturn('some_command')->shouldBeCalled();

        $validation = $dispatch($command->reveal());

        if ($validation instanceof Failure) {
            $this->fail($validation->toString());
        }
    }

    /**
     * @test
     */
    public function it_loads_state_from_empty_snapshot_store(): void
    {
        $snapshotStore = $this->prophesize(SnapshotStore::class);

        $message = $this->prophesize(Message::class);
        $message = $message->reveal();

        $definition = $this->prophesize(AggregateDefinition::class);
        $definition->aggregateType()->willReturn('test')->shouldBeCalled();
        $definition->extractAggregateId($message)->willReturn('42')->shouldBeCalled();

        $result = k\loadState($message, $definition->reveal(), $snapshotStore->reveal());

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

        $definition = $this->prophesize(AggregateDefinition::class);
        $definition->aggregateType()->willReturn('test')->shouldBeCalled();
        $definition->extractAggregateId($message)->willReturn('42')->shouldBeCalled();

        $result = k\loadState($message, $definition->reveal(), $snapshotStore);
        $this->assertEquals(['foo' => 'bar'], $result);
    }

    /**
     * @test
     */
    public function it_returns_early_when_loading_state_and_no_snapshot_store_given(): void
    {
        $message = $this->prophesize(Message::class);
        $message = $message->reveal();

        $definition = $this->prophesize(AggregateDefinition::class);

        $result = k\loadState($message, $definition->reveal(), null);
        $this->assertEquals([], $result);
    }

    /**
     * @test
     */
    public function it_loads_events_when_stream_not_found(): void
    {
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->hasStream(new StreamName('foo'))->willReturn(false)->shouldBeCalled();

        $result = k\loadEvents($eventStore->reveal(), new SingleStreamTestAggregateDefinition(), 'foo', 1);

        $this->assertInstanceOf(\EmptyIterator::class, $result);
    }

    /**
     * @test
     */
    public function it_loads_events_when_stream_found(): void
    {
        $streamName = new StreamName('foo');
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->hasStream($streamName)->willReturn(true)->shouldBeCalled();
        $eventStore->load($streamName, 1, null, null)->willReturn(new \ArrayIterator())->shouldBeCalled();

        $result = k\loadEvents($eventStore->reveal(), new SingleStreamTestAggregateDefinition(), 'foo', 1);

        $this->assertInstanceOf(\ArrayIterator::class, $result);
    }

    /**
     * @test
     */
    public function it_loads_events_when_stream_found_using_one_stream_per_aggregate(): void
    {
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->hasStream(Argument::type(StreamName::class))->willReturn(true)->shouldBeCalled();
        $eventStore->load(Argument::type(StreamName::class), 1, null, null)->willReturn(new \ArrayIterator())->shouldBeCalled();

        $result = k\loadEvents($eventStore->reveal(), new OneStreamPerAggregateTestAggregateDefinition(), 'foo', 1);

        $this->assertInstanceOf(\ArrayIterator::class, $result);
    }

    /**
     * @test
     */
    public function it_appends_to_stream_during_persist_when_stream_found(): void
    {
        $streamName = new StreamName('foo');
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->hasStream($streamName)->willReturn(true)->shouldBeCalled();
        $eventStore->appendTo($streamName, Argument::type(\Iterator::class))->shouldBeCalled();

        $message = $this->prophesize(Message::class);
        $raisedEvents = [$message->reveal()];

        $aggregateDefinition = $this->prophesize(AggregateDefinition::class);
        $aggregateDefinition->streamName()->willReturn(new StreamName('foo'))->shouldBeCalled();
        $aggregateDefinition->hasOneStreamPerAggregate()->willReturn(false)->shouldBeCalled();

        $validation = k\persistEvents(ImmList(...$raisedEvents), $eventStore->reveal(), $aggregateDefinition->reveal(), 'some_id');

        if ($validation instanceof Failure) {
            $this->fail($validation->toString());
        }
    }

    /**
     * @test
     */
    public function it_returns_early_on_persist_when_list_is_empty(): void
    {
        $streamName = new StreamName('foo');

        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->hasStream($streamName)->willReturn(true)->shouldNotBeCalled();
        $eventStore->appendTo($streamName, Argument::type(\Iterator::class))->shouldNotBeCalled();

        $raisedEvents = [];

        $aggregateDefinition = $this->prophesize(AggregateDefinition::class);
        $aggregateDefinition->streamName()->willReturn(new StreamName('foo'))->shouldNotBeCalled();
        $aggregateDefinition->hasOneStreamPerAggregate()->willReturn(false)->shouldNotBeCalled();

        $validation = k\persistEvents(ImmList(...$raisedEvents), $eventStore->reveal(), $aggregateDefinition->reveal(), 'some_id');

        if ($validation instanceof Failure) {
            $this->fail($validation->toString());
        }
    }

    /**
     * @test
     */
    public function it_persist_transactional_when_using_transactional_event_store(): void
    {
        $message = $this->prophesize(Message::class);
        $raisedEvents = [$message->reveal()];

        $aggregateDefinition = $this->prophesize(AggregateDefinition::class);
        $aggregateDefinition->streamName()->willReturn(new StreamName('foo'))->shouldBeCalled();
        $aggregateDefinition->hasOneStreamPerAggregate()->willReturn(false)->shouldBeCalled();

        $validation = k\persistEvents(ImmList(...$raisedEvents), new InMemoryEventStore(), $aggregateDefinition->reveal(), 'some_id');

        if ($validation instanceof Failure) {
            $this->fail($validation->toString());
        }
    }

    /**
     * @test
     */
    public function it_rolls_back_transaction_during_persist_when_using_transactional_event_store(): void
    {
        $this->expectException(\Exception::class);

        $eventStore = $this->prophesize(TransactionalEventStore::class);

        $eventStore->beginTransaction()->shouldBeCalledTimes(2);
        $eventStore->commit()->shouldBeCalledTimes(1);
        $eventStore->rollback()->shouldBeCalledTimes(1);
        $eventStore->hasStream(Argument::any())->willReturn(false, true)->shouldBeCalledTimes(2);
        $eventStore->create(Argument::any())->shouldBeCalledTimes(1);
        $eventStore->appendTo(Argument::any(), Argument::any())->willThrow(\Exception::class)->shouldBeCalledTimes(1);
        $eventStore = $eventStore->reveal();

        $message = $this->prophesize(Message::class);
        $message->metadata()->willReturn([]);
        $raisedEvents = [$message->reveal()];

        $aggregateDefinition = $this->prophesize(AggregateDefinition::class);
        $aggregateDefinition->streamName()->willReturn(new StreamName('foo'))->shouldBeCalled();
        $aggregateDefinition->hasOneStreamPerAggregate()->willReturn(true)->shouldBeCalled();

        k\persistEvents(ImmList(...$raisedEvents), $eventStore, $aggregateDefinition->reveal(), 'one');
        k\persistEvents(ImmList(...$raisedEvents), $eventStore, $aggregateDefinition->reveal(), 'one');
    }

    /**
     * @test
     */
    public function it_appends_to_stream_during_persist_when_stream_found_using_one_stream_per_aggregate(): void
    {
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->hasStream(Argument::type(StreamName::class))->willReturn(true)->shouldBeCalled();
        $eventStore->appendTo(Argument::type(StreamName::class), Argument::type(\Iterator::class))->shouldBeCalled();

        $message = $this->prophesize(Message::class);
        $raisedEvents = [$message->reveal()];

        $aggregateDefinition = $this->prophesize(AggregateDefinition::class);
        $aggregateDefinition->streamName()->willReturn(new StreamName('foo'))->shouldBeCalled();
        $aggregateDefinition->hasOneStreamPerAggregate()->willReturn(true)->shouldBeCalled();

        $validation = k\persistEvents(ImmList(...$raisedEvents), $eventStore->reveal(), $aggregateDefinition->reveal(), 'some_id');

        if ($validation instanceof Failure) {
            $this->fail($validation->toString());
        }
    }

    /**
     * @test
     * @group by
     */
    public function it_creates_stream_during_persist_when_no_stream_found_and_enriches_with_metadata(): void
    {
        $finalMessage = $this->prophesize(Message::class);

        $message = $this->prophesize(Message::class);
        $message->withAddedMetadata('key', 'value')->willReturn($finalMessage->reveal())->shouldBeCalled();

        $streamName = $this->prophesize(StreamName::class);
        $streamName = $streamName->reveal();

        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->hasStream($streamName)->willReturn(false)->shouldBeCalled();
        $eventStore->create(Argument::type(Stream::class))->shouldBeCalled();

        $aggregateDefinition = $this->prophesize(AggregateDefinition::class);
        $aggregateDefinition->streamName()->willReturn($streamName)->shouldBeCalled();
        $aggregateDefinition->hasOneStreamPerAggregate()->willReturn(false)->shouldBeCalled();
        $aggregateDefinition->extractAggregateVersion($message)->willReturn(5)->shouldBeCalled();
        $aggregateDefinition->metadataEnricher('some_id', 5, $message)->willReturn(new class() implements MetadataEnricher {
            public function enrich(Message $message): Message
            {
                return $message->withAddedMetadata('key', 'value');
            }
        }
        )->shouldBeCalled();
        $aggregateDefinition = $aggregateDefinition->reveal();

        $message = $message->reveal();

        $events = ImmList($message);

        $enricher = k\getEnricherFor($aggregateDefinition, 'some_id', $message);

        $events = $events->map($enricher);

        $validation = k\persistEvents($events, $eventStore->reveal(), $aggregateDefinition, 'some_id');

        if ($validation instanceof Failure) {
            $this->fail($validation->toString());
        }
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

        $result = k\getHandler($message->reveal(), $commandMap);

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

        k\getHandler($message->reveal(), $commandMap);
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

        k\getAggregateDefinition($message->reveal(), $commandMap);
    }
}
