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
use Prooph\Micro\Failure;
use Prooph\Micro\Pipe;
use Prooph\Micro\Success;

class PipeTest extends TestCase
{
    /**
     * @test
     */
    public function it_pipes(): void
    {
        $result = (new Pipe('aBC'))
            ->pipe('strtolower')
            ->pipe('ucfirst')
            ->result();

        $this->assertInstanceOf(Success::class, $result);
        $this->assertEquals('Abc', $result());
    }

    /**
     * @test
     */
    public function it_handles_failures(): void
    {
        $result = (new Pipe('aBC'))
            ->pipe(function () {
                return new Failure('something bad happened');
            })
            ->pipe('ucfirst')
            ->result();

        $this->assertInstanceOf(Failure::class, $result);
        $this->assertEquals('something bad happened', $result());
    }

    /**
     * @test
     */
    public function it_handles_failures_and_skips_further_callbacks(): void
    {
        ob_start();
        $result = (new Pipe('aBC'))
            ->pipe(function () {
                return new Failure('something bad happened');
            })
            ->pipe(function () {
                echo 'should not be executed';
            })
            ->result();

        $output = ob_get_clean();

        $this->assertEquals('', $output);
        $this->assertInstanceOf(Failure::class, $result);
        $this->assertEquals('something bad happened', $result());
    }

    /**
     * @test
     */
    public function it_handles_exceptions(): void
    {
        $result = (new Pipe('aBC'))
            ->pipe(function () {
                throw new \Exception('Exception there!');
            })
            ->pipe('ucfirst')
            ->result();

        $this->assertInstanceOf(Failure::class, $result);
        $this->assertEquals('Exception there!', $result());
    }

    /**
     * @test
     */
    public function it_uses_provided_failure_callback(): void
    {
        $result = (new Pipe('aBC', 'strtoupper'))
            ->pipe(function () {
                throw new \Exception('Exception there!');
            })
            ->pipe('ucfirst')
            ->result();

        $this->assertInstanceOf(Failure::class, $result);
        $this->assertEquals('EXCEPTION THERE!', $result());
    }
}
