<?php

/**
 * This file is part of the prooph/micro.
 * (c) 2017-2020 prooph software GmbH <contact@prooph.de>
 * (c) 2017-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\MicroExample\Model\Command;

final class ChangeUserName
{
    protected array $payload;

    public function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    public function messageName(): string
    {
        return 'change-username';
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function id(): string
    {
        return $this->payload()['id'];
    }

    public function name(): string
    {
        return $this->payload()['name'];
    }
}
