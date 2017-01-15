<?php

declare(strict_types = 1);

namespace Prooph\Micro;

use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\StreamName;

final class StreamMatcher
{
    /**
     * @var StreamName
     */
    private $streamName;

    /**
     * @var MetadataMatcher
     */
    private $metadataMatcher;

    /**
     * @var MetadataEnricher
     */
    private $metadataEnricher;

    public function __construct(
        StreamName $streamName,
        MetadataMatcher $metadataMatcher = null,
        MetadataEnricher $metadataEnricher = null
    ) {
        $this->streamName = $streamName;
        $this->metadataMatcher = $metadataMatcher;
        $this->metadataEnricher = $metadataEnricher;
    }

    public function streamName(): StreamName
    {
        return $this->streamName;
    }

    public function metadataMatcher(): ?MetadataMatcher
    {
        return $this->metadataMatcher;
    }

    public function metadataEnricher(): ?MetadataEnricher
    {
        return $this->metadataEnricher;
    }

}
