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
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreatePostgresCommand extends AbstractCommand
{
    use LockableTrait;

    protected function configure()
    {
        $this
            ->setName('micro:create:postgres')
            ->setDescription('Setup a PostgreSQL database')
            ->setHelp('This command creates a PostgreSQL database as service');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! $this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        if (! file_exists($this->getRootDir() . '/docker-compose.yml')) {
            $output->writeln('docker-compose.yml does not exist. Run ./bin/micro micro:setup. Aborted.');

            return 0;
        }

        $helper = $this->getHelper('question');

        $question = new Question('Name of the service (f.e. postgres): ');
        $question->setValidator(function ($answer) {
            if (! is_string($answer) || strlen($answer) === 0) {
                throw new \RuntimeException(
                    'Invalid service name'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $serviceName = $helper->ask($input, $output, $question);

        $question = new ChoiceQuestion('Which PostgreSQL image to use?', [
            'postgres:9.6-alpine',
            'postgres:9.6',
            'postgres:9.5-alpine',
            'postgres:9.5',
            'postgres:9.4-alpine',
            'postgres:9.4',
            'postgres:9.3-alpine',
            'postgres:9.3',
            'postgres:9.2-alpine',
            'postgres:9.2',
        ]);
        $question->setMaxAttempts(2);

        $image = $helper->ask($input, $output, $question);

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

        $question = new Question('Postgres port (defaults to "5432"): ', '5432');
        $question->setValidator(function ($answer) {
            if (! is_numeric($answer) || $answer === 0) {
                throw new \RuntimeException(
                    'Invalid MySQL port'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $dbPort = $helper->ask($input, $output, $question);

        $question = new Question('Postgres user (defaults to: "postgres"): ', 'postgres');

        $userName = $helper->ask($input, $output, $question);

        $question = new Question('Postgres password (defaults to ""): ', '');
        $question->setValidator(function ($answer) {
            if (! is_string($answer)) {
                throw new \RuntimeException(
                    'Invalid password'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $password = $helper->ask($input, $output, $question);

        $question = new Question('Mount docker-entrypoint-initdb.d (optional):', null);

        $initDb = $helper->ask($input, $output, $question);

        $question = <<<EOT
        
####################################################

Setup will be done with the following configuration:

Service name: $serviceName
Image: $image
Port: $dbPort
Database name: $dbName
User name: $userName
Password: $password

EOT;
        if ($initDb) {
            $question .= "docker-entrypoint-initdb.d: $initDb\n";
        }

        $question .= "\nAre those settings correct? (y/n):";

        $confirmation = new ConfirmationQuestion($question, false);

        if (! $helper->ask($input, $output, $confirmation)) {
            $output->writeln('Aborted');

            return 0;
        }

        $config = [
            'services' => [
                $serviceName => [
                    'image' => $image,
                    'ports' => [
                        "$dbPort:5432",
                    ],
                    'environment' => [
                        "POSTGRES_USER=$userName",
                        "POSTGRES_PASSWORD=$password",
                        "POSTGRES_DB=$dbName",
                    ],
                ],
            ],
        ];

        if (null !== $initDb) {
            $config['services'][$serviceName]['volumes'][] = "$initDb:/docker-entrypoint-initdb.d";
        }

        $this->updateConfig($serviceName, $config);

        $output->writeln('Successfully updated microservice settings');

        $this->release();
    }
}
