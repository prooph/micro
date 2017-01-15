<?php

declare(strict_types = 1);

namespace ProophExample\Micro\Model\Event;

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
