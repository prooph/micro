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

namespace Prooph\MicroExample\Model\Command;

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
