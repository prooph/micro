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

namespace Prooph\MicroExample\Infrastructure;

use Prooph\MicroExample\Model\UniqueEmailGuard;

final class InMemoryEmailGuard implements UniqueEmailGuard
{
    private $knownEmails = [];

    public function isUnique(string $email): bool
    {
        $isUnique = ! in_array($email, $this->knownEmails);

        if ($isUnique) {
            $this->knownEmails[] = $email;
        }

        return $isUnique;
    }
}
