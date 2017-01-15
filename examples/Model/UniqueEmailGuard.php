<?php

declare(strict_types = 1);

namespace ProophExample\Micro\Model;

interface UniqueEmailGuard
{
    public function isUnique(string $email): bool;
}
