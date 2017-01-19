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

namespace Prooph\MicroExample\Model\Event;

use Prooph\Common\Messaging\DomainEvent;
use Prooph\Common\Messaging\PayloadConstructable;
use Prooph\Common\Messaging\PayloadTrait;

final class UserWasRegistered extends DomainEvent implements PayloadConstructable
{
    use PayloadTrait;

    public function userId(): string
    {
        return $this->payload()['id'];
    }

    public function userName(): string
    {
        return $this->payload()['name'];
    }

    public function email(): string
    {
        return $this->payload()['email'];
    }
}
