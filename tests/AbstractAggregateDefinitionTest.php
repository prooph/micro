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
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Metadata\Operator;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AbstractAggregateDefiniton;

class AbstractAggregateDefinitionTest extends TestCase
{
    /**
     * @test
     */
    public function it_returns_identifier_and_version_name(): void
    {
        $this->assertEquals('id', $this->createDefinition()->identifierName());
        $this->assertEquals('version', $this->createDefinition()->versionName());
    }

    /**
     * @test
     */
    public function it_extracts_aggregate_id(): void
    {
        $message = $this->prophesize(Message::class);
        $message->payload()->willReturn(['id' => 'some_id'])->shouldBeCalled();

        $this->assertEquals('some_id', $this->createDefinition()->extractAggregateId($message->reveal()));
    }

    /**
     * @test
     */
    public function it_throws_exception_when_no_id_property_found_during_extraction(): void
    {
        $this->expectException(\RuntimeException::class);

        $message = $this->prophesize(Message::class);
        $message->payload()->willReturn([])->shouldBeCalled();

        $this->createDefinition()->extractAggregateId($message->reveal());
    }

    /**
     * @test
     */
    public function it_returns_metadata_matcher(): void
    {
        $metadataMatcher = $this->createDefinition()->metadataMatcher('some_id');

        $this->assertInstanceOf(MetadataMatcher::class, $metadataMatcher);

        $this->assertEquals(
            [
                [
                    'field' => '_aggregate_id',
                    'operator' => Operator::EQUALS(),
                    'value' => 'some_id',
                ],
            ],
            $metadataMatcher->data()
        );
    }

    /**
     * @test
     */
    public function it_returns_metadata_enricher(): void
    {
        $enricher = $this->createDefinition()->metadataEnricher('some_id');

        $this->assertInstanceOf(MetadataEnricher::class, $enricher);

        $enrichedMessage = $this->prophesize(Message::class);
        $enrichedMessage = $enrichedMessage->reveal();

        $message = $this->prophesize(Message::class);
        $message->withAddedMetadata('_aggregate_id', 'some_id')->willReturn($enrichedMessage)->shouldBeCalled();

        $result = $enricher->enrich($message->reveal());

        $this->assertSame($enrichedMessage, $result);
    }

    /**
     * @test
     */
    public function it_reconstitutes_state(): void
    {
        $message = $this->prophesize(Message::class);

        $state = $this->createDefinition()->reconstituteState(new \ArrayIterator([$message->reveal()]));

        $this->assertArrayHasKey('count', $state);
        $this->assertEquals(1, $state['count']);
    }

    public function createDefinition(): AbstractAggregateDefiniton
    {
        return new class() extends AbstractAggregateDefiniton {
            public function streamName(string $aggregateId): StreamName
            {
                return new StreamName('foo');
            }

            public function aggregateType(): string
            {
                return 'foo';
            }

            public function apply(array $state, Message ...$events): array
            {
                if (! isset($state['count'])) {
                    $state['count'] = 0;
                }

                foreach ($events as $event) {
                    ++$state['count'];
                }

                return $state;
            }
        };
    }
}
