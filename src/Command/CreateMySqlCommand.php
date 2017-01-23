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

class CreateMySqlCommand extends AbstractCommand
{
    use LockableTrait;

    protected function configure()
    {
        $this
            ->setName('micro:create:mysql')
            ->setDescription('Setup a MySQL database')
            ->setHelp('This command creates a MySQL database as service');
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

        $question = new Question('Name of the service (f.e. mysql): ');
        $question->setValidator(function ($answer) {
            if (! is_string($answer) || ! preg_match('/^[a-z-0-9]+$/', $answer)) {
                throw new \RuntimeException(
                    'Invalid service name'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $serviceName = $helper->ask($input, $output, $question);

        $question = new ChoiceQuestion('Which MySQL image to use?', [
            'mysql:5.7',
            'mysql:5.6',
            'mysql:5.5',
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

        $question = new Question('MySQL port (defaults to "3306"): ', '3306');
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

        $question = new Question('MySQL root password (defaults to ""): ', '');

        $mysqlRoot = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion('Do you want to create antoher MySQL user? (y/n) ', false);

        $answer = $helper->ask($input, $output, $question);

        if ($answer) {
            $question = new Question('MySQL user: ', '');
            $question->setValidator(function ($answer) {
                if (! is_string($answer) || strlen($answer) === 0) {
                    throw new \RuntimeException(
                        'Invalid user'
                    );
                }

                return $answer;
            });
            $question->setMaxAttempts(2);

            $userName = $helper->ask($input, $output, $question);

            $question = new Question('MySQL password: ', '');
            $question->setValidator(function ($answer) {
                if (! is_string($answer) || strlen($answer) === 0) {
                    throw new \RuntimeException(
                        'Invalid password'
                    );
                }

                return $answer;
            });
            $question->setMaxAttempts(2);

            $password = $helper->ask($input, $output, $question);
        }

        $question = new Question('Mount docker-entrypoint-initdb.d (optional): ', null);

        $initDb = $helper->ask($input, $output, $question);

        $question = <<<EOT
        
####################################################

Setup will be done with the following configuration:

Service name: $serviceName
Image: $image
Port: $dbPort
Database name: $dbName
Root password: $mysqlRoot

EOT;
        if ($initDb) {
            $question .= "docker-entrypoint-initdb.d: $initDb\n";
        }

        if ($answer) {
            $question .= "User name: $userName\nPassword: $password\n";
        }

        $question .= "\nAre those settings correct? (y/n): ";

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
                        "$dbPort:3306",
                    ],
                    'environment' => [
                        "MYSQL_ROOT_PASSWORD=$mysqlRoot",
                        "MYSQL_DATABASE=$dbName",
                    ],
                    'labels' => [
                        'prooph-pdo' => true,
                    ],
                ],
            ],
        ];

        if ($answer) {
            $config['services'][$serviceName]['environment'][] = "MYSQL_USER=$userName";
            $config['services'][$serviceName]['environment'][] = "MYSQL_PASSWORD=$password";
        }

        if (empty($mysqlRoot)) {
            $config['services'][$serviceName]['environment'][] = 'MYSQL_ALLOW_EMPTY_PASSWORD=yes';
        }

        if (null !== $initDb) {
            $config['services'][$serviceName]['volumes'][] = "$initDb:/docker-entrypoint-initdb.d";
        }

        $this->updateConfig($serviceName, $config);

        $output->writeln('Successfully updated microservice settings');

        $this->release();
    }
}
