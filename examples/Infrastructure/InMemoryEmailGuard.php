<?php

declare(strict_types = 1);

namespace ProophExample\Micro\Infrastructure;

use ProophExample\Micro\Model\UniqueEmailGuard;

final class InMemoryEmailGuard implements UniqueEmailGuard
{
    private $knownEmails = [];

    public function isUnique(string $email): bool
    {
        $isUnique = !in_array($email, $this->knownEmails);

        if ($isUnique) {
            $this->knownEmails[] = $email;
        }

        return $isUnique;
    }
}
