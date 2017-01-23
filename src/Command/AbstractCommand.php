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
            return __DIR__ . '/../../';
        } elseif (file_exists(__DIR__ . '/../../../../autoload.php')) {
            return __DIR__ . '/../../../../../';
        }
    }

    protected function updateConfig(string $serviceName, array $config): void
    {
        $configFileName = $this->getRootDir() . 'docker-compose.yml';
        $configFile = file_get_contents($configFileName);

        if (! preg_match('/^.+\n.+\n# gateway: (.+)\n# service: (.+)\n/', $configFile, $matches)) {
            $error = <<<EOT
docker-compose.yml is damaged! The first 4 lines should look similar to this:

# Generated prooph-micro docker-compose.yml file
# Do not edit the first 4 comment lines, they are used by the micro-cli tool
# gateway: gateway
# service: service

Aborted!

EOT;

            throw new \RuntimeException($error);
        }

        $gateway = $matches[1];
        $service = $matches[2];

        $oldConfig = Yaml::parse($configFile);

        if (array_key_exists($serviceName, $oldConfig['services'])) {
            throw new \RuntimeException('The requested service name "' . $serviceName . '" exists already.');
        }

        $newConfig = array_merge_recursive($oldConfig, $config);

        $newConfigString = <<<EOT
# Generated prooph-micro docker-compose.yml file
# Do not edit the first 4 comment lines, they are used by the micro-cli tool
# gateway: $gateway
# service: $service

EOT;

        $newConfigString .= Yaml::dump($newConfig, 10, 2);

        file_put_contents($configFileName, $newConfigString);
    }
}
