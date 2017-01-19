<?php

declare(strict_types=1);

namespace ProophExample\Micro\Model\Command;

use Prooph\Common\Messaging\Command;

final class UnknownCommand extends Command
{
    protected $messageName = 'unknown command';

    protected function setPayload(array $payload): void
    {
        // do nothing
    }

    public function payload(): array
    {
        return [];
    }
}
