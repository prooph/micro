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
use Prooph\Micro\AggregateDefinition;
use Prooph\Micro\SnapshotReadModel;
use Prooph\SnapshotStore\InMemorySnapshotStore;
use Prooph\SnapshotStore\Snapshot;
use Prooph\SnapshotStore\SnapshotStore;

class SnapshotReadModelTest extends TestCase
{
    /**
     * @test
     */
    public function it_stacks_and_persists_when_snapshot_exists(): void
    {
        $message1 = $this->prophesize(Message::class);
        $message1 = $message1->reveal();

        $message2 = $this->prophesize(Message::class);
        $message2 = $message2->reveal();

        $snapshotStore = new InMemorySnapshotStore();
        $snapshotStore->save(new Snapshot('message', 'some_id', ['foo' => 'bar', 'version' => 1], 1, new \DateTimeImmutable()));

        $definition = $this->prophesize(AggregateDefinition::class);
        $definition->versionName()->willReturn('version')->shouldBeCalled();
        $definition->extractAggregateId($message1)->willReturn('some_id')->shouldBeCalled();
        $definition->extractAggregateId($message2)->willReturn('some_id')->shouldBeCalled();
        $definition->aggregateType()->willReturn('message')->shouldBeCalled();
        $definition->apply(['foo' => 'bar', 'version' => 1], $message1)->willReturn(['foo' => 'baz', 'version' => 2])->shouldBeCalled();
        $definition->apply(['foo' => 'baz', 'version' => 2], $message2)->willReturn(['foo' => 'bam', 'version' => 3])->shouldBeCalled();

        $readModel = new SnapshotReadModel($snapshotStore, $definition->reveal());

        $readModel->stack('apply', $message1);
        $readModel->stack('apply', $message2);

        $readModel->persist();
    }

    /**
     * @test
     */
    public function it_stacks_and_persists_when_snapshot_does_not_exist(): void
    {
        $message1 = $this->prophesize(Message::class);
        $message1 = $message1->reveal();

        $message2 = $this->prophesize(Message::class);
        $message2 = $message2->reveal();

        $snapshotStore = new InMemorySnapshotStore();

        $definition = $this->prophesize(AggregateDefinition::class);
        $definition->versionName()->willReturn('version')->shouldBeCalled();
        $definition->extractAggregateId($message1)->willReturn('some_id')->shouldBeCalled();
        $definition->extractAggregateId($message2)->willReturn('some_id')->shouldBeCalled();
        $definition->aggregateType()->willReturn('message')->shouldBeCalled();
        $definition->apply([], $message1)->willReturn(['foo' => 'bar', 'version' => 1])->shouldBeCalled();
        $definition->apply(['foo' => 'bar', 'version' => 1], $message2)->willReturn(['foo' => 'baz', 'version' => 2])->shouldBeCalled();

        $readModel = new SnapshotReadModel($snapshotStore, $definition->reveal());

        $readModel->stack('apply', $message1);
        $readModel->stack('apply', $message2);

        $readModel->persist();
    }

    /**
     * @test
     */
    public function it_throws_exception_during_stack_when_invalid_event_type_given(): void
    {
        $this->expectException(\RuntimeException::class);

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $definition = $this->prophesize(AggregateDefinition::class);

        $readModel = new SnapshotReadModel($snapshotStore->reveal(), $definition->reveal());

        $readModel->stack('foo', 'bar');
    }

    /**
     * @test
     */
    public function it_is_already_initialized(): void
    {
        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $definition = $this->prophesize(AggregateDefinition::class);

        $readModel = new SnapshotReadModel($snapshotStore->reveal(), $definition->reveal());

        $this->assertTrue($readModel->isInitialized());
    }

    /**
     * @test
     */
    public function it_cannot_init(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $definition = $this->prophesize(AggregateDefinition::class);

        $readModel = new SnapshotReadModel($snapshotStore->reveal(), $definition->reveal());

        $readModel->init();
    }

    /**
     * @test
     */
    public function it_cannot_reset(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $definition = $this->prophesize(AggregateDefinition::class);

        $readModel = new SnapshotReadModel($snapshotStore->reveal(), $definition->reveal());

        $readModel->reset();
    }

    /**
     * @test
     */
    public function it_cannot_delete(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $snapshotStore = $this->prophesize(SnapshotStore::class);
        $definition = $this->prophesize(AggregateDefinition::class);

        $readModel = new SnapshotReadModel($snapshotStore->reveal(), $definition->reveal());

        $readModel->delete();
    }
}
