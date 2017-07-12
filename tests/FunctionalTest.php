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
        $result = f\pipe('strtolower', 'ucfirst')('aBC');

        $this->assertSame('Abc', $result);
    }

    /**
     * @test
     */
    public function it_handles_exceptions_in_pipe(): void
    {
        $result = f\pipe(function () {
            throw new \Exception('Exception there!');
        }, 'ucfirst')('aBC');

        $this->assertInstanceOf(\Exception::class, $result);
        $this->assertSame('Exception there!', $result->getMessage());
    }

    /**
     * @test
     */
    public function it_pipes_in_right_order(): void
    {
       $result = f\pipe('strtolower', 'strtoupper')('aBc');

       $this->assertSame('ABC', $result);
    }

    /**
     * @test
     */
    public function it_composes(): void
    {
        $result = f\compose('ucfirst', 'strtolower')('aBC');

        $this->assertSame('Abc', $result);
    }

    /**
     * @test
     */
    public function it_handles_exceptions_in_compose(): void
    {
        $result = f\compose(function () {
            throw new \Exception('Exception there!');
        }, 'ucfirst')('aBC');

        $this->assertInstanceOf(\Exception::class, $result);
        $this->assertSame('Exception there!', $result->getMessage());
    }

    /**
     * @test
     */
    public function it_composes_in_right_order(): void
    {
        $result = f\compose('strtoupper', 'strtolower')('aBc');

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
}
