<?php

declare(strict_types=1);

namespace Prooph\Micro;

class Pipe
{
    /**
     * @var mixed
     */
    private $argument;

    public function pipe(callable $action): Pipe
    {
        $this->argument = $action($this->argument);

        return $this;
    }

    public function result()
    {
        return $this->argument;
    }
}
