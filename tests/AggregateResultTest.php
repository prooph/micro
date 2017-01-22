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
use Prooph\Micro\AggregateResult;

class AggregateResultTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_empty_aggregate_result(): void
    {
        $result = new AggregateResult([]);

        $this->assertEquals([], $result->raisedEvents());
        $this->assertEquals([], $result->state());
    }

    /**
     * @test
     */
    public function it_creates_aggregate_result(): void
    {
        $message = $this->prophesize(Message::class);
        $message->messageType()->willReturn(Message::TYPE_EVENT)->shouldBeCalled();

        $result = new AggregateResult(['foo' => 'bar'], $message->reveal());

        $this->assertNotEmpty($result->raisedEvents());
        $this->assertEquals(['foo' => 'bar'], $result->state());
    }

    /**
     * @test
     */
    public function it_throws_exception_when_invalid_message_type_returned(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $message = $this->prophesize(Message::class);
        $message->messageType()->willReturn(Message::TYPE_COMMAND)->shouldBeCalled();

        new AggregateResult(['foo' => 'bar'], $message->reveal());
    }
}
