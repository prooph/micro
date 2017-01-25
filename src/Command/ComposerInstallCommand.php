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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

final class ComposerInstallCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('micro:composer:install')
            ->setDescription('Install composer dependencies for services');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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

            $process->setTimeout(0);
            $process->setIdleTimeout(30);

            $processHelper->run($output, $process);
        }

        return 0;
    }
}
