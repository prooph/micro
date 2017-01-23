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

use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class SetupCommand extends AbstractCommand
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

        if (file_exists($this->getRootDir() . '/docker-compose.yml')) {
            $output->writeln('docker-compose.yml exists already. Aborted.');

            return;
        }

        $output->writeln('This setup will use the following docker-images:');
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

        $question = <<<EOT
        
####################################################

Setup will be done with the following configuration:

Gateway directory: $gatewayDirectory
Service directory: $serviceDirectory
HTTP port: $httpPort
HTTPS port: $httpsPort

Are those settings correct? (y/n): 
EOT;

        $confirmation = new ConfirmationQuestion($question, false);

        if (! $helper->ask($input, $output, $confirmation)) {
            $output->writeln('Aborted');

            return;
        }

        file_put_contents(
            $this->getRootDir() . '/docker-compose.yml',
            $this->generateConfigFile(
                $gatewayDirectory,
                $serviceDirectory,
                $httpPort,
                $httpsPort
            )
        );

        $output->writeln('Successfully created microservice settings');

        $this->release();
    }

    private function generateConfigFile(
        string $gatewayDirectory,
        string $serviceDirectory,
        string $httpPort,
        string $httpsPort
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
      - ./$gatewayDirectory:/etc/nginx/sites-enabled:ro

EOT;

        return $config;
    }
}
