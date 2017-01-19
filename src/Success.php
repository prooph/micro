<?php

declare(strict_types=1);

namespace Prooph\Micro;

final class Success implements Result
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __invoke()
    {
        return $this->value;
    }
}
