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
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $io = new SymfonyStyle($input, $output);

        if (! $this->lock()) {
            $io->warning('The command is already running in another process. Aborted.');

            return 1;
        }

        if (! file_exists($this->getRootDir() . '/docker-compose.yml')) {
            $io->warning('docker-compose.yml does not exist. Run ./bin/micro micro:setup. Aborted.');

            $this->release();

            return 1;
        }

        $question = new Question('Name of the service (f.e. postgres): ');
        $question->setValidator(function ($answer) {
            if (! is_string($answer) || ! preg_match('/^[a-z-0-9]+$/', $answer)) {
                throw new \RuntimeException(
                    'Invalid service name'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $serviceName = $io->askQuestion($question);
        $configMessages[] = '<info>Service name:</info> '.$serviceName;

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

        $image = $io->askQuestion($question);
        $configMessages[] = '<info>PostgreSQL image:</info> '.$image;

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

        $dbName = $io->askQuestion($question);
        $configMessages[] = '<info>Database name:</info> '.$dbName;

        $question = new Question('PostgreSQL port: ', 'random');
        $question->setValidator(function ($answer) {
            if ('random' === $answer) {
                return '';
            }

            if (! is_int((int) $answer) || 0 === (int) $answer) {
                throw new \RuntimeException(
                    'Invalid PostgreSQL port'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $dbPort = $io->askQuestion($question);
        $configMessages[] = '<info>Database port:</info> '.($dbPort ?: 'random');

        $question = new Question('PostgreSQL user: ', 'postgres');

        $userName = $io->askQuestion($question);
        $configMessages[] = '<info>User:</info> '.$userName;

        $question = new Question('PostgreSQL password: ', '');
        $question->setValidator(function ($answer) {
            if (! is_string($answer)) {
                throw new \RuntimeException(
                    'Invalid password'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $password = $io->askQuestion($question);
        $configMessages[] = '<info>Password:</info> '.$password;

        $question = new Question('Mount docker-entrypoint-initdb.d: ', '');
        $question->setValidator(function ($answer) {
            if (! is_string($answer)) {
                throw new \RuntimeException(
                    'Invalid docker-entrypoint-initdb.d'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $initDb = $io->askQuestion($question);

        $configMessages[] = '<info>docker-entrypoint-initdb.d:</info> '.$initDb;

        $io->section('Setup will be done with the following configuration:');
        $io->listing($configMessages);

        if (! $io->confirm('Are those settings correct?', false)) {
            $io->writeln('<comment>Aborted</comment>');

            $this->release();

            return 1;
        }

        $config = [
            'services' => [
                $serviceName => [
                    'image' => $image,
                    'environment' => [
                        "POSTGRES_USER=$userName",
                        "POSTGRES_PASSWORD=$password",
                        "POSTGRES_DB=$dbName",
                    ],
                    'labels' => [
                        'prooph-pdo' => true,
                    ],
                ],
            ],
        ];

        if ($dbPort === 'random') {
            $config['services'][$serviceName]['ports'] = [
                '5432',
            ];
        }

        if ($dbPort !== '5432') {
            $config['services'][$serviceName]['ports'] = [
                "$dbPort:5432",
            ];
        }

        if (null !== $initDb) {
            $config['services'][$serviceName]['volumes'][] = "$initDb:/docker-entrypoint-initdb.d";
        }

        $this->updateConfig($serviceName, $config);

        $io->success('Successfully updated microservice settings');

        $this->release();

        return 0;
    }
}
