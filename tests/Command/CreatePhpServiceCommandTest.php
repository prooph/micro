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
use Prooph\Micro\Command\CreatePhpServiceCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CreatePhpServiceCommandTest extends TestCase
{
    /**
     * @var CreatePhpServiceCommand
     */
    private $command;

    public function setUp(): void
    {
        $this->prepareTempDirectory();

        /** @var CreatePhpServiceCommand $command */
        $command = $this->getMockBuilder(CreatePhpServiceCommand::class)
            ->setMethods(['getRootDir'])
            ->getMock();

        $command
            ->method('getRootDir')
            ->willReturn($this->getTempDirectory());

        $application = new Application();
        $application->add($command);

        $this->command = $application->get($command->getName());
    }

    public function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @test
     *
     * @see https://github.com/prooph/micro/issues/20
     */
    public function it_adds_command_value_in_docker_compose_file(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['test', '0', '', '0', 'run_this_command', 'yes']);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertContains(
            'command: run_this_command',
            file_get_contents($this->getTempDirectory() . '/docker-compose.yml')
        );
    }

    private function getTempDirectory(): string
    {
        return sys_get_temp_dir() . '/prooph_test';
    }

    private function prepareTempDirectory(): void
    {
        if (! is_dir($this->getTempDirectory() . '/gateway')) {
            mkdir($this->getTempDirectory() . '/gateway', 0777, true);
        }

        file_put_contents($this->getTempDirectory() . '/docker-compose.yml', "version: '2'\nservices: []");
    }

    private function removeTempDirectory(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->getTempDirectory(), \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            $fileInfo->isDir() ? rmdir($fileInfo->getRealPath()) : unlink($fileInfo->getRealPath());
        }

        rmdir($this->getTempDirectory());
    }
}
