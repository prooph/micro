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

namespace Prooph\Micro\Command;

use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ProcessBuilder;

final class ComposerInstallCommand extends AbstractCommand
{
    private const DEFAULT_TIMEOUT = 0;
    private const DEFAULT_IDLE_TIMEOUT = 30;

    protected function configure()
    {
        $this
            ->setName('micro:composer:install')
            ->setDescription('Install composer dependencies for services')
            ->addArgument('service', InputArgument::OPTIONAL)
            ->addOption('all', '-a', InputOption::VALUE_NONE)
            ->addOption(
                'timeout',
                '-t',
                InputOption::VALUE_REQUIRED,
                'Sets the process timeout (max. runtime) per service in seconds',
                self::DEFAULT_TIMEOUT
            )
            ->addOption(
                'idle-timeout',
                '-i',
                InputOption::VALUE_REQUIRED,
                'Sets the process idle timeout (max. time since last output) per service in seconds',
                self::DEFAULT_IDLE_TIMEOUT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $declaredPhpServices = $this->getDeclaredPhpServices();

        if (! $declaredPhpServices) {
            $io->warning('No php services declared in docker-compose.yml. Aborting');

            return 1;
        }

        $requestedServices = $this->getRequestedServices($input, $io, $declaredPhpServices);

        $timeout = (int) $input->getOption('timeout');
        $idleTimeout = (int) $input->getOption('idle-timeout');

        $processBuilder = new ProcessBuilder();
        $processBuilder->setPrefix($this->getDockerComposeExecutable());
        $processBuilder->setTimeout($timeout);

        /** @var ProcessHelper $processHelper */
        $processHelper = $this->getHelper('process');

        $serviceDirPath = $this->getRootDir() . '/' . self::SERVICE_DIR_PATH;

        foreach ($requestedServices as $service => $values) {
            $io->newLine(2);
            $io->section("Run `docker-compose install` for service $service");

            $processBuilder->setArguments([
                'run',
                '--rm',
                '-i',
                '-e',
                'COMPOSER_ALLOW_SUPERUSER=1',
                '--volume',
                sprintf('%s/%s:/app:rw', $serviceDirPath, $service),
                'prooph/composer:'. $values['php_version'],
                'install',
                '--no-interaction',
                '--no-suggest',
            ]);

            $process = $processBuilder->getProcess();
            $process->setIdleTimeout($idleTimeout);

            $processHelper->mustRun($output, $process, null, function ($type, $buffer) use ($io) {
                $io->write("$buffer");
            });
        }

        return 0;
    }

    private function getDeclaredPhpServices(): array
    {
        $phpServices = [];
        $dockerComposeConfig = $this->getDockerComposeConfig();

        foreach ($dockerComposeConfig['services'] as $service => $serviceConfig) {
            if (! isset($serviceConfig['image'])) {
                continue;
            }

            if (! preg_match('/^prooph\/php:([0-9\.]+)/', $serviceConfig['image'], $phpVersionMatches)) {
                continue;
            }

            $phpServices[$service] = [
                'php_version' => $phpVersionMatches[1],
            ];
        }

        return $phpServices;
    }

    private function getRequestedServices(InputInterface $input, OutputStyle $io, array $requestedServices): array
    {
        if ($input->getOption('all')) {
            return $requestedServices;
        }

        $requestedService = $input->getArgument('service');

        if ($requestedService && ! array_key_exists($requestedService, $requestedServices)) {
            $io->warning("Service with name '$requestedService' is not configured in docker-compose.yml yet.");
            $requestedService = null;
        }

        if (! $requestedService) {
            $requestedService = $io->choice('Select a service', array_keys($requestedServices));
        }

        if (! array_key_exists($requestedService, $requestedServices)) {
            throw new \RuntimeException('Invalid service name provided.');
        }

        return [$requestedService => $requestedServices[$requestedService]];
    }

    private function getDockerComposeExecutable(): string
    {
        return '/usr/local/bin/docker';
    }
}
