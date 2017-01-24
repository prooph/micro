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
     * @var SetupCommand
     */
    private $command;

    public function setUp(): void
    {
        $this->prepareTempDirectory();

        /** @var SetupCommand $command */
        $command = $this->getMockBuilder(SetupCommand::class)
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
     */
    public function it_creates_config_files(): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['80', '443', 'y']);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertTrue(file_exists($this->getTempDirectory() . '/gateway/www.conf'));
        $this->assertTrue(file_exists($this->getTempDirectory() . '/docker-compose.yml'));
    }

    /**
     * @test
     * @dataProvider getValidInputParameters
     */
    public function it_answers_with_success_exit_code(array $validInputParameters): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs($validInputParameters);
        $commandTester->execute([]);

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    public function getValidInputParameters(): array
    {
        return [
            [['', '', 'y']],
            [['80', '443', 'y']],
            [['8080', '443', 'y']],
            [['', '443', 'y']],
            [['random', '443', 'y']],
            [['80', '', 'y']],
            [['80', 'random', 'y']],
        ];
    }

    /**
     * @test
     * @dataProvider getInvalidInputParameters
     */
    public function it_answers_with_error_exit_code(array $invalidInputParameters): void
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs($invalidInputParameters);
        $commandTester->execute([]);

        $this->assertSame(1, $commandTester->getStatusCode());
    }

    public function getInvalidInputParameters(): array
    {
        return [
            [['', '', '', 'n']],
        ];
    }

    /**
     * @test
     */
    public function it_exits_if_docker_compose_file_already_exists(): void
    {
        touch($this->getTempDirectory() . '/docker-compose.yml');

        $commandTester = new CommandTester($this->command);
        $commandTester->execute([]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertRegExp('/docker-compose.yml exists already/', $commandTester->getDisplay());
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
