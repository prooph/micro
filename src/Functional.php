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

namespace Prooph\Micro\Functional;

/* @todo this could also be moved to a new repository: prooph/functional */

use ArrayIterator;
use InvalidArgumentException;
use ReflectionFunction;
use Traversable;

interface Just
{
    public function get();
}

interface Nothing
{
}

class Maybe
{
    final public static function nothing()
    {
        return new class() extends Maybe implements Nothing {
        };
    }

    final public static function just($value)
    {
        return new class($value) extends Maybe implements Just {
            private $value;

            public function __construct($value)
            {
                $this->value = $value;
            }

            public function get()
            {
                return $this->value;
            }
        };
    }
}

interface Left
{
    public function get();
}

interface Right
{
    public function get();
}

class Either
{
    final public static function left($value)
    {
        return new class($value) extends Either implements Left {
            private $value;

            public function __construct($value)
            {
                $this->value = $value;
            }

            public function get()
            {
                return $this->value;
            }
        };
    }

    final public static function right($value)
    {
        return new class($value) extends Either implements Right {
            private $value;

            public function __construct($value)
            {
                $this->value = $value;
            }

            public function get()
            {
                return $this->value;
            }
        };
    }
}

interface Success
{
}

interface Failure
{
    public function reason(): string;
}

class Attempt // aka Try, but this is reserved
{
    final public static function success()
    {
        return new class() extends Attempt implements Success {
        };
    }

    final public static function failure(string $reason)
    {
        return new class($reason) extends Attempt implements Failure {
            private $reason;

            public function __construct($reason)
            {
                $this->reason = $reason;
            }

            public function reason(): string
            {
                return $this->reason;
            }
        };
    }
}

const curry = 'Prooph\Micro\Functional\curry';

function curry(callable $f, ...$args): callable
{
    return function (...$partialArgs) use ($f, $args) {
        return (function ($args) use ($f) {
            return count($args) < (new ReflectionFunction($f))->getNumberOfRequiredParameters()
                ? curry($f, ...$args)
                : $f(...$args);
        })(array_merge($args, $partialArgs));
    };
}

const o = 'Prooph\Micro\Functional\o';

// the little circle
// (b -> c) -> (a -> b) -> (a -> c)
// remember function composition os associative:
// h o (g o f) = (h o g) o f
function o(callable $g): callable
{
    return function (callable $f) use ($g): callable {
        return function ($x) use ($g, $f) {
            return $g($f($x));
        };
    };
}

const id = 'Prooph\Micro\Functional\id';

// The identity function
// a -> a
function id($x)
{
    return $x;
}

const pipe = 'Prooph\Micro\Functional\pipe';

function pipe(array $functions): callable
{
    return function ($x = null) use ($functions) {
        return array_reduce(
            $functions,
            function ($accumulator, callable $callback) {
                return $callback($accumulator);
            },
            $x
        );
    };
}

const compose = 'Prooph\Micro\Functional\compose';

function compose(array $functions): callable
{
    return function ($x = null) use ($functions) {
        return array_reduce(
            array_reverse($functions),
            function ($accumulator, callable $callback) {
                return $callback($accumulator);
            },
            $x
        );
    };
}

const Y = 'Prooph\Micro\Functional\Y';

// The Y fixed point combinator
function Y(callable $f): callable
{
    return
        (function (callable $x) use ($f): callable {
            return $f(function ($v) use ($x) {
                return $x($x)($v);
            });
        })(function (callable $x) use ($f): callable {
            return $f(function ($v) use ($x) {
                return $x($x)($v);
            });
        });
}

const foldl = 'Prooph\Micro\Functional\foldl';

function foldl($v): callable
{
    return curry(function (callable $f, $l) use ($v) {
        $useIterator = false;

        if ($l instanceof \Traversable) {
            $l = iterator_to_array($l);
            $useIterator = true;
        }

        if (! is_array($l)) {
            throw new \InvalidArgumentException('foldl expects an array or Traversable');
        }

        $result = array_reduce(
            $l,
            $f,
            $v
        );

        if ($useIterator) {
            $result = new ArrayIterator($result);
        }

        return $result;
    });
}

const foldr = 'Prooph\Micro\Functional\foldr';

function foldr($v): callable
{
    return curry(function (callable $f, $l) use ($v) {
        $useIterator = false;

        if ($l instanceof Traversable) {
            $l = iterator_to_array($l);
            $useIterator = true;
        }

        if (! is_array($l)) {
            throw new InvalidArgumentException('foldr expects an array or Traversable');
        }

        $result = array_reduce(
            array_reverse($l),
            $f,
            $v
        );

        if ($useIterator) {
            $result = new ArrayIterator($result);
        }

        return $result;
    });
}

const map = 'Prooph\Micro\Functional\map';

function map(callable $f): callable
{
    return function ($l) use ($f) {
        $useIterator = false;

        if ($l instanceof Traversable) {
            $l = iterator_to_array($l);
            $useIterator = true;
        }

        if (! is_array($l)) {
            throw new InvalidArgumentException('map expects an array or Traversable');
        }

        $result = array_map($f, $l);

        if ($useIterator) {
            $result = new ArrayIterator($result);
        }

        return $result;
    };
}

const memoize = 'Prooph\Micro\Functional\memoize';

// memoizes callbacks and returns their value instead of calling them
function memoize(callable $f = null)
{
    return function ($a = []) use ($f) {
        static $storage = [];

        if ($f === null) {
            $storage = [];

            return null;
        }

        $key = null;

        if (is_callable($a)) {
            $key = $a;
            $a = [];
        } elseif (! is_array($a) && ! $a instanceof Traversable) {
            return new InvalidArgumentException('Arguments must be an array, Traversable or a callback');
        }

        static $keyGenerator = null;

        if (! $keyGenerator) {
            $keyGenerator = function ($v) use (&$keyGenerator) {
                $type = gettype($v);

                if ($type === 'array') {
                    $key = implode(':', map($keyGenerator)($v));
                } elseif ($type === 'object') {
                    $key = get_class($v) . ':' . spl_object_hash($v);
                } else {
                    $key = (string) $v;
                }

                return $key;
            };
        }

        if ($key === null) {
            $key = $keyGenerator(array_merge([$f], $a));
        } else {
            $key = $keyGenerator($key);
        }

        if (! isset($storage[$key]) && ! array_key_exists($key, $storage)) {
            $storage[$key] = curry($f, $a);
        }

        return $storage[$key];
    };
}
