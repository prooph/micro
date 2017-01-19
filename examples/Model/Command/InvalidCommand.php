<?php

declare(strict_types=1);

namespace Prooph\MicroExample\Model\Command;

use Prooph\Common\Messaging\Command;

final class InvalidCommand extends Command
{
    protected function setPayload(array $payload): void
    {
        // do nothing
    }

    public function payload(): array
    {
        return [];
    }
}
