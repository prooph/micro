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

namespace ProophTest\Micro;

use PHPUnit\Framework\TestCase;
use Prooph\Micro\Functional as f;

class FunctionalTest extends TestCase
{
    /**
     * @test
     */
    public function it_pipes(): void
    {
        $result = f\pipe(['strtolower', 'ucfirst'])('aBC');

        $this->assertSame('Abc', $result);
    }

    /**
     * @test
     */
    public function it_handles_exceptions_in_pipe(): void
    {
        $result = f\pipe([function () {
            throw new \Exception('Exception there!');
        }, 'ucfirst'])('aBC');

        $this->assertInstanceOf(\Exception::class, $result);
        $this->assertSame('Exception there!', $result->getMessage());
    }

    /**
     * @test
     */
    public function it_pipes_in_right_order(): void
    {
        $result = f\pipe(['strtolower', 'strtoupper'])('aBc');

        $this->assertSame('ABC', $result);
    }

    /**
     * @test
     */
    public function it_composes(): void
    {
        $result = f\compose(['ucfirst', 'strtolower'])('aBC');

        $this->assertSame('Abc', $result);
    }

    /**
     * @test
     */
    public function it_handles_exceptions_in_compose(): void
    {
        $result = f\compose([function () {
            throw new \Exception('Exception there!');
        }, 'ucfirst'])('aBC');

        $this->assertInstanceOf(\Exception::class, $result);
        $this->assertSame('Exception there!', $result->getMessage());
    }

    /**
     * @test
     */
    public function it_composes_in_right_order(): void
    {
        $result = f\compose(['strtoupper', 'strtolower'])('aBc');

        $this->assertSame('ABC', $result);
    }

    /**
     * @test
     */
    public function it_returns_identity(): void
    {
        $result = f\id(4);

        $this->assertSame(4, $result);
    }

    /**
     * @test
     */
    public function it_composes_two_functions(): void
    {
        $f = function ($x) {
            return $x + 1;
        };

        $g = function ($x) {
            return $x + 2;
        };

        $h = f\o($g)($f);

        $result = $h(1);

        $this->assertSame(4, $result);
    }

    /**
     * @test
     */
    public function it_curries(): void
    {
        $map = f\curry('array_map');

        $addOne = function ($x) {
            return $x + 1;
        };

        $addOneToList = $map($addOne);

        $result = $addOneToList([1, 2, 3, 4, 5]);

        $this->assertSame(
            [2, 3, 4, 5, 6],
            $result
        );
    }

    /**
     * @test
     */
    public function it_curries_2(): void
    {
        $filter = f\curry(function (callable $f, $l): array {
            return array_filter($l, $f);
        });

        $isEven = function (int $x): bool {
            return $x % 2 === 0;
        };

        $filterEven = $filter($isEven);

        $this->assertSame([2, 4], array_values($filterEven([1, 2, 3, 4, 5])));
    }

    /**
     * @test
     */
    public function it_combines_using_little_circle(): void
    {
        $g = function ($x) {
            return $x + 3;
        };

        $f = function ($x) {
            return $x + 5;
        };

        $h = f\o($f)($g);

        $result1 = $h(1);

        $g2 = function ($x) {
            return $x + 10;
        };

        $f2 = function ($x) {
            return $x + 20;
        };

        $h2 = f\o($f2)($g2);

        $result2 = $h2($result1);

        $h3 = f\o($h)($h2);

        $result3 = $h3(1);

        $h4 = f\o($h2)($h);

        $result4 = $h4(1);

        $this->assertSame(9, $result1);
        $this->assertSame(39, $result2);
        $this->assertSame($result2, $result3);
        $this->assertSame($result2, $result4);
    }

    /**
     * @test
     */
    public function it_Y_cominates_using_factorial_example(): void
    {
        $factorial = f\Y(function (callable $fact) {
            return function (int $n) use ($fact) {
                return ($n <= 1) ? 1 : $n * $fact($n - 1);
            };
        });

        $this->assertSame(720, $factorial(6));
    }

    /**
     * @test
     */
    public function it_folds_left(): void
    {
        $foldl = f\foldl(0)(f\curry(function ($x, $y) {
            return $x + $y;
        }));

        $result = $foldl([1, 2, 3]);

        $this->assertSame(6, $result);
    }

    /**
     * @test
     */
    public function it_folds_right(): void
    {
        $foldr = f\foldr(0)(f\curry(function ($x, $y) {
            return $x + $y;
        }));

        $result = $foldr([1, 2, 3]);

        $this->assertSame(6, $result);
    }

    /**
     * @test
     */
    public function it_maps_array(): void
    {
        $map = f\map(function ($x) {
            return $x + 1;
        });

        $result = $map([1, 2, 3, 4, 5]);

        $this->assertSame([2, 3, 4, 5, 6], $result);

        $result2 = $map(new \ArrayIterator([1, 2, 3, 4, 5]));

        $this->assertEquals(new \ArrayIterator([2, 3, 4, 5, 6]), $result2);
    }
}
