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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractCommand extends Command
{
    protected function getRootDir(): string
    {
        if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            return realpath(__DIR__ . '/../../');
        } elseif (file_exists(__DIR__ . '/../../../../autoload.php')) {
            return realpath(__DIR__ . '/../../../../../');
        }
    }

    protected function getServiceDirPath($serviceName): string
    {
        return $this->getRootDir() . '/service/' . $serviceName;
    }

    protected function getDockerComposeConfig(): array
    {
        $configFileName = $this->getRootDir() . '/docker-compose.yml';
        $configFile = file_get_contents($configFileName);

        return Yaml::parse($configFile);
    }

    protected function updateConfig(string $serviceName, array $config): void
    {
        $configFileName = $this->getRootDir() . '/docker-compose.yml';
        $configFile = file_get_contents($configFileName);

        $oldConfig = Yaml::parse($configFile);

        if (array_key_exists($serviceName, $oldConfig['services'])) {
            throw new \RuntimeException('The requested service name "' . $serviceName . '" exists already.');
        }

        file_put_contents($configFileName, Yaml::dump(array_merge_recursive($oldConfig, $config), 10, 2));
    }
}
