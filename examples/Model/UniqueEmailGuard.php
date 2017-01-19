<?php

declare(strict_types=1);

namespace Prooph\MicroExample\Model;

interface UniqueEmailGuard
{
    public function isUnique(string $email): bool;
}
