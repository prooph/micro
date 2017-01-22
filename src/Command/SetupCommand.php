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
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class SetupCommand extends Command
{
    use LockableTrait;

    protected function configure()
    {
        $this
            ->setName('micro:setup')
            ->setDescription('Setup a prooph-micro application')
            ->setHelp('This command creates the skeleton for a prooph-micro(services) application.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! $this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        if (file_exists($this->getRootDir() . 'docker-compose.yml')) {
            $output->writeln('docker-compose.yml exists already. Aborted.');

            return;
        }

        $output->writeln('This setup will use the following docker-images:');
        $output->writeln('prooph/php:7.1-fpm');
        $output->writeln('prooph/nginx:www (as webserver)');
        $output->writeln('postgres:alpine (as event store database)');
        $output->writeln('');

        $helper = $this->getHelper('question');
        $question = new Question('Gateway directory (defaults to "gateway"): ', 'gateway');
        $question->setValidator(function ($answer) {
            if (! is_string($answer) || strlen($answer) === 0) {
                throw new \RuntimeException(
                    'Gateway directory cannot be empty'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $gatewayDirectory = $helper->ask($input, $output, $question);

        $question = new Question('Service directory (defaults to "service"): ', 'service');
        $question->setValidator(function ($answer) {
            if (! is_string($answer) || strlen($answer) === 0) {
                throw new \RuntimeException(
                    'Service directory cannot be empty'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $serviceDirectory = $helper->ask($input, $output, $question);

        $question = new Question('HTTP Port (defaults to "80"): ', '80');
        $question->setValidator(function ($answer) {
            if (! is_int((int) $answer) || $answer === 0) {
                throw new \RuntimeException(
                    'Invalid HTTP Port'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $httpPort = $helper->ask($input, $output, $question);

        $question = new Question('HTTPS Port (defaults to "443"): ', '443');
        $question->setValidator(function ($answer) {
            if (! is_int((int) $answer) || $answer === 0) {
                throw new \RuntimeException(
                    'Invalid HTTPS Port'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $httpsPort = $helper->ask($input, $output, $question);

        $question = new Question('Postgres Port (defaults to "5432"): ', '5432');
        $question->setValidator(function ($answer) {
            if (! is_int((int) $answer) || $answer === 0) {
                throw new \RuntimeException(
                    'Invalid Postgres Port'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $postgresPort = $helper->ask($input, $output, $question);

        $question = new Question('Postgres database name: ', false);
        $question->setValidator(function ($answer) {
            if (! is_string($answer) || strlen($answer) === 0) {
                throw new \RuntimeException(
                    'Postgres database name cannot be empty'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $postgresDbName = $helper->ask($input, $output, $question);

        $question = <<<EOT
        
###################################

Setup will be done with the following configuration:

Gateway directory: $gatewayDirectory
Service directory: $serviceDirectory
HTTP Port: $httpPort 
HTTPS Port: $httpsPort 
Postgres Port: $postgresPort 
Postgres database name: $postgresDbName

Are those settings correct? (y/n): 
EOT;

        $confirmation = new ConfirmationQuestion($question, false);

        if (! $helper->ask($input, $output, $confirmation)) {
            $output->writeln('Aborted');

            return;
        }

        file_put_contents(
            $this->getRootDir() . 'docker-compose.yml',
            $this->generateConfigFile(
                $gatewayDirectory,
                $serviceDirectory,
                $httpPort,
                $httpsPort,
                $postgresPort,
                $postgresDbName
            )
        );

        $output->writeln('Successfully created microservice settings');

        $this->release();
    }

    private function generateConfigFile(
        string $gatewayDirectory,
        string $serviceDirectory,
        string $httpPort,
        string $httpsPort,
        string $postgresPort,
        string $postgresDbName
    ): string {
        return <<<EOT
# Generated prooph-micro docker-compose.yml file
# Do not edit the first 4 comment lines, they are used by the micro-cli tool
# gateway: $gatewayDirectory
# service: $serviceDirectory
version: '2'

services:
  nginx:
    image: prooph/nginx:www
    ports:
      - $httpPort:80
      - $httpsPort:443
    volumes:
      - ./gateway:/etc/nginx/sites-enabled:ro

  postgres:
    image: postgres:alpine
    ports:
      - $postgresPort:5432
    environment:
      - POSTGRES_DB=$postgresDbName
    volumes:
      - ./packages/shared/vendor/prooph/pdo-event-store/scripts/postgres:/docker-entrypoint-initdb.d

EOT;
    }

    private function getRootDir(): string
    {
        if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            return __DIR__ . '/../../';
        } elseif (file_exists(__DIR__ . '/../../../../autoload.php')) {
            return __DIR__ . '/../../../../../';
        }
    }
}
