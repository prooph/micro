<?php

declare(strict_types = 1);

namespace ProophExample\Micro\Model\Command;

use Prooph\Common\Messaging\Command;
use Prooph\Common\Messaging\PayloadConstructable;
use Prooph\Common\Messaging\PayloadTrait;

final class RegisterUser extends Command implements PayloadConstructable
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
