<?php
/**
 * This file is part of the prooph/micro.
 * (c) 2017-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2017-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Prooph\Micro\Command;

use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

final class ComposerInstallCommand extends AbstractCommand
{
    const DEFAULT_TIMEOUT = 0;
    const DEFAULT_IDLE_TIMEOUT = 30;

    protected function configure()
    {
        $this
            ->setName('micro:composer:install')
            ->setDescription('Install composer dependencies for services')
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
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeout = (int) $input->getOption('timeout');
        $idleTimeout = (int) $input->getOption('idle-timeout');

        $dockerComposeConfig = $this->getDockerComposeConfig();
        $phpServices = [];

        foreach ($dockerComposeConfig['services'] as $service => $serviceConfig) {
            if (isset($serviceConfig['image']) && preg_match('/^prooph\/php:([0-9\.]+)/', $serviceConfig['image'],
                    $matches)
            ) {
                $phpServices[$service] = $matches[1];
            }
        }

        $servicePath = $this->getRootDir() . '/service';

        /** @var ProcessHelper $processHelper */
        $processHelper = $this->getHelper('process');

        foreach ($phpServices as $service => $phpVersion) {
            $process = ProcessBuilder::create([
                '/usr/local/bin/docker',
                'run',
                '--rm',
                '-i',
                '--volume',
                "$servicePath/$service:/app",
                '-e',
                'COMPOSER_ALLOW_SUPERUSER=1',
                "prooph/composer:$phpVersion",
                'install',
                '--no-interaction',
                '--no-suggest',
                '--optimize-autoloader',
            ])
                ->getProcess();

            $process->setTimeout($timeout);
            $process->setIdleTimeout($idleTimeout);

            $processHelper->run($output, $process);
        }

        return 0;
    }
}
