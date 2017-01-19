<?php

declare(strict_types=1);

namespace Prooph\Micro;

class Pipe
{
    /**
     * @var mixed
     */
    private $argument;

    private $failure = false;

    /**
     * @var callable
     */
    private $failureCallback;

    public function __construct($argument, callable $onFailure = null)
    {
        $this->argument = $argument;
        $this->failureCallback = $onFailure;
    }

    public function pipe(callable $action): Pipe
    {
        if ($this->failure) {
            return $this;
        }

        try {
            if (($argument = $this->argument) instanceof Result) {
                $argument = $argument();
            }
            $result = $action($argument);
        } catch (\Throwable $throwable) {
            $result = new Failure($throwable->getMessage());
        }

        if (! $result instanceof Result) {
            $result = new Success($result);
        }

        if ($result instanceof Failure) {
            $this->failure = true;

            if (null !== $this->failureCallback) {
                $callback = $this->failureCallback;
                $callback($result());
            }
        }

        $this->argument = $result;

        return $this;
    }

    public function result()
    {
        return $this->argument;
    }
}
