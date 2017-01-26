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
    private const DEFAULT_TIMEOUT = 0;
    private const DEFAULT_IDLE_TIMEOUT = 30;

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

        $phpServices = $this->getDeclaredPhpServices();
        $serviceDirPath = $this->getRootDir() . '/' . self::SERVICE_DIR_PATH;

        $processBuilder = new ProcessBuilder();
        $processBuilder->setPrefix('/usr/local/bin/docker run --rm -i -e COMPOSER_ALLOW_SUPERUSER=1');
        $processBuilder->setTimeout($timeout);

        /** @var ProcessHelper $processHelper */
        $processHelper = $this->getHelper('process');

        foreach ($phpServices as $service => $values) {

            $processBuilder->setArguments([
                '--volume',
                sprintf('%s/%s:%s', $serviceDirPath, $service, $values['working_dir']),
                'prooph/composer:'. $values['php_version'],
                'install',
                '--no-interaction',
                '--no-suggest',
                '--optimize-autoloader',
            ]);

            $process = $processBuilder->getProcess();
            $process->setIdleTimeout($idleTimeout);

            $processHelper->run($output, $process);
        }

        return 0;
    }

    private function getDeclaredPhpServices(): array
    {
        $phpServices = [];
        $dockerComposeConfig = $this->getDockerComposeConfig();

        foreach ($dockerComposeConfig['services'] as $service => $serviceConfig) {
            if (!isset($serviceConfig['image'], $serviceConfig['volumes']) || !is_array($serviceConfig['volumes'])) {
                // not all neccessary service parameters are available. Service skipped
                continue;
            }

            if (!preg_match('/^prooph\/php:([0-9\.]+)/', $serviceConfig['image'], $phpVersionMatches)) {
                continue;
            }

            $workingDirRegexp = sprintf('/%s:([^:])/', self::SERVICE_DIR_PATH . '/' . $service);
            foreach ($serviceConfig['volumes'] as $volume) {
                if (preg_match($workingDirRegexp, $serviceConfig['image'], $workingDirMatches)) {
                    break;
                }
            }

            if (!isset($workingDirMatches)) {
                continue;
            }

            $phpServices[$service] = [
                'php_version' => $phpVersionMatches[1],
                'working_dir' => $workingDirMatches[1],
            ];
        }

        return $phpServices;
    }
}
