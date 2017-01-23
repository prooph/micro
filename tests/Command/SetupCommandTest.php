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

namespace ProophTest\Micro\Command;

use PHPUnit\Framework\TestCase;
use Prooph\Micro\Command\SetupCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SetupCommandTest extends TestCase
{
    /**
     * @var Application
     */
    private $application;

    public function setUp(): void
    {
        $this->application = new Application();

        $setupCommand = $this->getMockBuilder(SetupCommand::class)
            ->setMethods(['getRootDir'])
            ->getMock();

        $setupCommand->method('getRootDir')->willReturn(sys_get_temp_dir());

        $this->application->add($setupCommand);
    }

    public function tearDown(): void
    {
        unlink(sys_get_temp_dir() . '/gateway/www.conf');
        unlink(sys_get_temp_dir() . '/docker-compose.yml');
    }

    /**
     * @test
     */
    public function it_creates_config_files(): void
    {
        $command = $this->application->find('micro:setup');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs([
            'gateway',
            '80',
            '443',
            'y',
        ]);

        $commandTester->execute(['command' => $command->getName()]);

        $this->assertTrue(file_exists(sys_get_temp_dir() . '/gateway/www.conf'));
        $this->assertTrue(file_exists(sys_get_temp_dir() . '/docker-compose.yml'));
    }
}
