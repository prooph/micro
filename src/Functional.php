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

use ReflectionFunction;
use Throwable;

const curry = 'Prooph\Micro\Functional\curry';

function curry(callable $f, callable ...$fs)
{
    return function (...$partialArgs) use ($f, $fs) {
        return (function ($args) use ($f) {
            return count($args) < (new ReflectionFunction($f))->getNumberOfRequiredParameters()
                ? curry($f, ...$args)
                : $f(...$args);
        })(array_merge($fs, $partialArgs));
    };
}

const o = 'Prooph\Micro\Functional\o';

function o(callable $g): callable
{
    return function (callable $f) use ($g): callable {
        return function ($x) use ($g, $f) {
            return $g($f($x));
        };
    };
}

const id = 'Prooph\Micro\Functional\id';

function id($x)
{
    return $x;
}

const pipe = 'Prooph\Micro\Functional\pipe';

function pipe(callable ...$functions): callable
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

function compose(callable ...$functions): callable
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
