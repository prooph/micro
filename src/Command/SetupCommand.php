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

use Madkom\NginxConfigurator\Builder;
use Madkom\NginxConfigurator\Config\Location;
use Madkom\NginxConfigurator\Config\Server;
use Madkom\NginxConfigurator\Node\Directive;
use Madkom\NginxConfigurator\Node\Param;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class SetupCommand extends AbstractCommand
{
    use LockableTrait;

    protected function configure()
    {
        $this
            ->setName('micro:setup')
            ->setDescription('Setup a prooph-micro application')
            ->setHelp('This command creates the skeleton for a prooph-micro(services) application.')
            ->addOption('no-ports', null, InputOption::VALUE_NONE, 'Do not publish ports on docker host.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (! $this->lock()) {
            $io->warning('The command is already running in another process. Aborted.');

            return 1;
        }

        if (file_exists($this->getRootDir() . '/docker-compose.yml')) {
            $io->warning('docker-compose.yml exists already. Aborted.');

            $this->release();

            return 1;
        }

        $io->section('This setup will use the following docker-images:');
        $io->listing(['prooph/nginx:www (as webserver)']);

        $httpPort = null;
        $httpsPort = null;

        if (! $input->getOption('no-ports')) {
            $question = new Question('HTTP port: ', 'random');
            $question->setValidator(function ($answer) {
                if ('random' === $answer) {
                    return '';
                }

                if (! is_int((int) $answer) || 0 === (int) $answer) {
                    throw new \RuntimeException(
                        'Invalid HTTP port'
                    );
                }

                return $answer;
            });

            $question->setMaxAttempts(2);

            $httpPort = $io->askQuestion($question);
            $configMessages[] = '<info>HTTP port:</info> '.($httpPort ?: 'random');

            $question = new Question('HTTPS port: ', 'random');
            $question->setValidator(function ($answer) {
                if ('random' === $answer) {
                    return '';
                }

                if (! is_int((int) $answer) || 0 === (int) $answer) {
                    throw new \RuntimeException(
                        'Invalid HTTPS port'
                    );
                }

                return $answer;
            });

            $question->setMaxAttempts(2);

            $httpsPort = $io->askQuestion($question);
            $configMessages[] = '<info>HTTPS port:</info> '.($httpsPort ?: 'random');
        }

        $io->section('Setup will be done with the following configuration:');
        $io->listing($configMessages);

        if (! $io->confirm('Are those settings correct?', false)) {
            $io->writeln('<comment>Aborted</comment>');

            $this->release();

            return 1;
        }

        file_put_contents(
            $this->getRootDir() . '/docker-compose.yml',
            $this->generateConfigFile(
                $httpPort,
                $httpsPort
            )
        );

        $this->generateNginxConfig()->dumpFile($this->getRootDir() . '/gateway/www.conf');

        $io->success('Successfully created microservice settings');

        $this->release();

        return 0;
    }

    private function generateConfigFile(
        string $httpPort = null,
        string $httpsPort = null
    ): string {
        $config = [
            'version' => '2',
            'services' => [
                'nginx' => [
                    'image' => 'prooph/nginx:www',
                    'volumes' => [
                        './gateway:/etc/nginx/sites-enabled:ro',
                    ],
                ],
            ],
        ];

        if (null !== $httpPort) {
            $config['services']['nginx']['ports'][] = '' !== $httpPort ? "$httpPort:80" : '80';
        }

        if (null !== $httpsPort) {
            $config['services']['nginx']['ports'][] = '' !== $httpsPort ? "$httpsPort:443" : '443';
        }

        return Yaml::dump($config, 4);
    }

    private function generateNginxConfig(): Builder
    {
        $builder = new Builder();

        $server = $builder->append(new Server([new Directive('listen', [new Param('80')])]));

        $server->append(new Directive('listen', [new Param('443 ssl http2')]));
        $server->append(new Directive('server_name', [new Param('localhost')]));
        $server->append(new Directive('root', [new Param('/var/www/public')]));
        $server->append(new Directive('index', [new Param('index.php')]));
        $server->append(new Directive('include', [new Param('conf.d/basic.conf')]));
        $server->append(new Directive('server_name', [new Param('localhost')]));
        $server->append(new Location(new Param('/'), null, [
            new Directive('try_files', [new Param('\$uri \$uri/ 404')]),
        ]));

        return $builder;
    }
}
