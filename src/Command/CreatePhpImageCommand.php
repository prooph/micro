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

class CreatePhpImageCommand extends AbstractCommand
{
    use LockableTrait;

    protected function configure()
    {
        $this
            ->setName('micro:create:php-image')
            ->setDescription('Setup a php image')
            ->setHelp('This command creates a php-image a service');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! $this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        if (! file_exists($this->getRootDir() . 'docker-compose.yml')) {
            $output->writeln('docker-compose.yml does not exist. Run ./bin/micro micro:setup. Aborted.');

            return 0;
        }

        $helper = $this->getHelper('question');

        $question = new Question('Name of the service (f.e. php-fpm): ');
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

        $question = new ChoiceQuestion('Which PHP version to use?', [
            '7.1',
            '7.0',
            '5.6',
        ]);
        $question->setMaxAttempts(2);

        $version = $helper->ask($input, $output, $question);

        $images = [
            'prooph/php:' . $version . '-cli',
            'prooph/php:' . $version . '-cli-blackfire',
            'prooph/php:' . $version . '-cli-opcache',
            'prooph/php:' . $version . '-cli-xdebug',
            'prooph/php:' . $version . '-fpm',
        ];

        if ('5.6' !== $version) {
            $images[] = 'prooph/php:' . $version . '-fpm-blackfire';
        }

        $images[] = 'prooph/php:' . $version . '-fpm-opcache';
        $images[] = 'prooph/php:' . $version . '-fpm-xdebug';
        $images[] = 'prooph/php:' . $version . '-fpm-zray';

        $question = new ChoiceQuestion('Which PHP image to use?', $images);
        $question->setMaxAttempts(2);

        $image = $helper->ask($input, $output, $question);

        $question = <<<EOT
        
####################################################

Setup will be done with the following configuration:

Service name: $serviceName
Image: $image

Are those settings correct? (y/n): 
EOT;

        $confirmation = new ConfirmationQuestion($question, false);

        if (! $helper->ask($input, $output, $confirmation)) {
            $output->writeln('Aborted');

            return 0;
        }

        $config = [
            'services' => [
                $serviceName => [
                    'image' => $image,
                ],
            ],
        ];

        $this->updateConfig($serviceName, $config);

        $output->writeln('Successfully updated microservice settings');

        $this->release();
    }
}
