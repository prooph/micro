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
use Throwable;
use Traversable;

const curry = 'Prooph\Micro\Functional\curry';

function curry(callable $f, ...$args)
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
        try {
            return array_reduce(
                $functions,
                function ($accumulator, callable $callback) {
                    return $callback($accumulator);
                },
                $x
            );
        } catch (Throwable $e) {
            return $e;
        }
    };
}

const compose = 'Prooph\Micro\Functional\compose';

function compose(array $functions): callable
{
    return function ($x = null) use ($functions) {
        try {
            return array_reduce(
                array_reverse($functions),
                function ($accumulator, callable $callback) {
                    return $callback($accumulator);
                },
                $x
            );
        } catch (Throwable $e) {
            return $e;
        }
    };
}

const Y = 'Prooph\Micro\Functional\Y';

// The Y fixed point combinator
function Y(callable $f)
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
            return new \InvalidArgumentException('foldl expects an array or Traversable');
        }

        try {
            $result = array_reduce(
                $l,
                $f,
                $v
            );

            if ($useIterator) {
                $result = new ArrayIterator($result);
            }

            return $result;
        } catch (Throwable $e) {
            return $e;
        }
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
            return new InvalidArgumentException('foldl expects an array or Traversable');
        }

        try {
            $result = array_reduce(
                array_reverse($l),
                $f,
                $v
            );

            if ($useIterator) {
                $result = new ArrayIterator($result);
            }

            return $result;
        } catch (Throwable $e) {
            return $e;
        }
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
            return new InvalidArgumentException('map expects an array or Traversable');
        }

        $result = array_map($f, $l);

        if ($useIterator) {
            $result = new ArrayIterator($result);
        }

        return $result;
    };
}
