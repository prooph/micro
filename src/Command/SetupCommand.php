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
use Symfony\Component\Console\Question\ChoiceQuestion;
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

        $question = new Question('HTTP port (defaults to "80"): ', '80');
        $question->setValidator(function ($answer) {
            if (! is_int((int) $answer) || $answer === 0) {
                throw new \RuntimeException(
                    'Invalid HTTP port'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $httpPort = $helper->ask($input, $output, $question);

        $question = new Question('HTTPS port (defaults to "443"): ', '443');
        $question->setValidator(function ($answer) {
            if (! is_int((int) $answer) || $answer === 0) {
                throw new \RuntimeException(
                    'Invalid HTTPS port'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $httpsPort = $helper->ask($input, $output, $question);

        $question = new ChoiceQuestion('Choose an event-store database (defaults to PostgreSQL)', ['MySQL', 'PostgreSQL'], 1);

        $database = $helper->ask($input, $output, $question);

        if ('PostgreSQL' === $database) {
            $question = new Question('PostgreSQL port (defaults to "5432"): ', '5432');
            $question->setValidator(function ($answer) {
                if (! is_int((int) $answer) || $answer === 0) {
                    throw new \RuntimeException(
                        'Invalid PostgreSQL port'
                    );
                }

                return $answer;
            });
            $question->setMaxAttempts(2);

            $dbPort = $helper->ask($input, $output, $question);

            $mysqlRoot = null;
        } else {
            $question = new Question('MySQL port (defaults to "3306"): ', '3306');
            $question->setValidator(function ($answer) {
                if (! is_int((int) $answer) || $answer === 0) {
                    throw new \RuntimeException(
                        'Invalid MySQL port'
                    );
                }

                return $answer;
            });
            $question->setMaxAttempts(2);

            $dbPort = $helper->ask($input, $output, $question);

            $question = new Question('MySQL root password (defaults to ""): ', '');

            $mysqlRoot = $helper->ask($input, $output, $question);
        }

        $question = new Question('Database name: ', false);
        $question->setValidator(function ($answer) {
            if (! is_string($answer) || strlen($answer) === 0) {
                throw new \RuntimeException(
                    'Database name cannot be empty'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $dbName = $helper->ask($input, $output, $question);

        $question = <<<EOT
        
###################################

Setup will be done with the following configuration:

Gateway directory: $gatewayDirectory
Service directory: $serviceDirectory
HTTP port: $httpPort
HTTPS port: $httpsPort
Event-Store-Database: $database
Database name: $dbName

EOT;
        if ('PostgreSQL' === $database) {
            $question .= "Postgres port: $dbPort\n";
        } else {
            $question .= "MySQL port: $dbPort\nMySQL root password: $mysqlRoot\n";
        }

        $question .= "\nAre those settings correct? (y/n):";

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
                $database,
                $dbPort,
                $dbName,
                $mysqlRoot
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
        string $database,
        string $dbPort,
        string $dbName,
        string $mysqlRoot = null
    ): string {
        $config = <<<EOT
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

EOT;

        if ('PostgreSQL' === $database) {
            $config .= <<<EOT
  postgres:
    image: postgres:alpine
    ports:
      - $dbPort:5432
    environment:
      - POSTGRES_DB=$dbName
    volumes:
      - ./packages/shared/vendor/prooph/pdo-event-store/scripts/postgres:/docker-entrypoint-initdb.d
EOT;
        } else {
            $config .= <<<EOT
  mysql:
    image: mysql
    ports:
     - $dbPort:3306
    environment:
      - MYSQL_ROOT_PASSWORD=$mysqlRoot
      - MYSQL_DATABASE=$dbName
EOT;

            if (empty($mysqlRoot)) {
                $config .= "\n      - MYSQL_ALLOW_EMPTY_PASSWORD=yes";
            }

            $config .= "\n";
        }

        return $config;
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
