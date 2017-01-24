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
use Madkom\NginxConfigurator\Config\Upstream;
use Madkom\NginxConfigurator\Node\Directive;
use Madkom\NginxConfigurator\Node\Node;
use Madkom\NginxConfigurator\Node\Param;
use Madkom\NginxConfigurator\Parser;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class CreatePhpServiceCommand extends AbstractCommand
{
    use LockableTrait;

    protected function configure()
    {
        $this
            ->setName('micro:create:php-service')
            ->setDescription('Setup a php service')
            ->setHelp('This command creates a new php-service');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (! $this->lock()) {
            $io->warning('The command is already running in another process. Aborted.');

            return 1;
        }

        try {
            if (! file_exists($this->getRootDir() . '/docker-compose.yml')) {
                $io->warning('docker-compose.yml does not exist. Run ./bin/micro micro:setup. Aborted.');

                $this->release();

                return 1;
            }

            $currentConfig = Yaml::parse(file_get_contents($this->getRootDir() . '/docker-compose.yml'));

            $question = new Question('Name of the service: ');
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

            $question = new ChoiceQuestion('Which PHP image to use?', [
                'prooph/php:7.1-cli',
                'prooph/php:7.1-fpm',
                'prooph/php:7.0-cli',
                'prooph/php:7.0-fpm',
                'prooph/php:5.6-cli',
                'prooph/php:5.6-fpm',
            ]);
            $question->setMaxAttempts(2);

            $image = $io->askQuestion($question);

            $question = new Question('Give the directory in which to mount the service (defaults to: "/var/www")', '/var/www');
            $question->setValidator(function ($answer) {
                if (! is_string($answer) || strlen($answer) === 0) {
                    throw new \RuntimeException(
                        'Invalid service name'
                    );
                }

                return $answer;
            });
            $question->setMaxAttempts(2);

            $volume = $io->askQuestion($question);

            $services = array_merge(['[NONE]'], array_keys($currentConfig['services']));
            $question = new ChoiceQuestion('On which services is the new service dependend? (comma separated)', $services);
            $question->setMultiselect(true);

            $dependsOn = $io->askQuestion($question);

            if ($dependsOn === ['[NONE]']) {
                $dependsOn = [];
            }

            $useNginxConfig = false;

            if ('-fpm' === substr($image, -4, 4)) {
                $useNginxConfig = true;

                $io->section('Nginx configuration');
                do {
                    $question = new Question('Add upstream (f.e. "php-user-GET"): ');
                    $question->setValidator(function ($answer) {
                        if (! is_string($answer) || strlen($answer) === 0) {
                            throw new \RuntimeException(
                                'Invalid upstream'
                            );
                        }

                        return $answer;
                    });
                    $question->setMaxAttempts(2);

                    $upStreams[] = $io->askQuestion($question);
                } while ($io->confirm('Add more upstreams?', false));

                do {
                    $question = new Question('Add location (f.e. "= /api/v1/user-$request_method"): ');
                    $question->setValidator(function ($answer) {
                        if (! is_string($answer) || 0 === strlen($answer)) {
                            throw new \RuntimeException(
                                'Invalid location'
                            );
                        }

                        return $answer;
                    });
                    $question->setMaxAttempts(2);

                    $locations[] = $io->askQuestion($question);

                    $question = new ChoiceQuestion('Add fastcgi_pass: ', $upStreams);

                    $fastcgiPasses[] = $io->askQuestion($question);

                    $question = new Question('Add fastcgi_index (defaults to "index.php"): ', 'index.php');
                    $fastcgiIndexes[] = $io->askQuestion($question);

                    $question = new Question('Add fastcgi_param SCRIPT_FILENAME (defaults to "\'/var/www/public/index.php\': "): ', '\'/var/www/public/index.php\'');
                    $scriptFileNames[] = $io->askQuestion($question);

                    $question = new Question('Add fastcgi_param PATH_INFO (defaults to "\'/\'"): ', '\'/\'');
                    $pathInfos[] = $io->askQuestion($question);

                    $question = new Question('Add fastcgi_param SCRIPT_NAME (defaults to "\'/index.php\'"): ', '\'/index.php\'');
                    $scriptNames[] = $io->askQuestion($question);
                } while ($io->confirm('Do you want to add another nginx location?', false));
            } else {
                $question = new Question('Enter the start command (f.e. "php run.php"): ', '');
                $question->setValidator(function ($answer) {
                    if (! is_string($answer) || strlen($answer) === 0) {
                        throw new \RuntimeException(
                            'Invalid command'
                        );
                    }

                    return $answer;
                });
                $question->setMaxAttempts(2);

                $command = $io->askQuestion($question);

                $restart = $io->confirm('Always restart container if it exists?', false);
            }

            $dependsOnString = '';
            foreach ($it = new \CachingIterator(new \ArrayIterator($dependsOn), \CachingIterator::FULL_CACHE) as $dependend) {
                $dependsOnString .= "  - $dependend";
                if ($it->hasNext()) {
                    $dependsOnString .= "\n";
                }
            }

            if ($useNginxConfig) {
                $gatewayFile = $this->getRootDir() . '/gateway/www.conf';

                $parser = new Parser();

                $nginxConfig = $parser->parseFile($gatewayFile);

                $builder = new Builder();
                $builder->append($nginxConfig);

                $servers = $nginxConfig->search(function (Node $node) {
                    return $node instanceof Server;
                });

                $count = count($servers);

                if ($count === 0) {
                    throw new \RuntimeException('No server section found in gateway config.');
                }

                if ($count > 1) {
                    throw new \RuntimeException('More than one server section found in gateway config.');
                }

                $server = $servers->getIterator()->current();

                /* @var Server $server */
                foreach ($upStreams as $upStream) {
                    $builder->append(new Upstream(new Param($upStream), [
                        new Directive('server', [new Param("$serviceName:9000")]),
                    ]));
                }

                while ($location = array_shift($locations)) {
                    $fastcgiPass = array_shift($fastcgiPasses);
                    $fastcgiIndex = array_shift($fastcgiIndexes);
                    $scriptFileName = array_shift($scriptFileNames);
                    $pathInfo = array_shift($pathInfos);
                    $scriptName = array_shift($scriptNames);

                    $server->append(new Location(new Param($location), null, [
                        new Directive('fastcgi_split_path_info', [new Param('^(.+\.php)(/.+)$')]),
                        new Directive('fastcgi_pass', [new Param($fastcgiPass)]),
                        new Directive('fastcgi_index', [new Param($fastcgiIndex)]),
                        new Directive('include', [new Param('fastcgi_params')]),
                        new Directive('fastcgi_param', [new Param('SCRIPT_FILENAME ' . $scriptFileName)]),
                        new Directive('fastcgi_param', [new Param('PATH_INFO ' . $pathInfo)]),
                        new Directive('fastcgi_param', [new Param('SCRIPT_NAME ' . $scriptName)]),
                    ]));
                }

                $builder->dumpFile($gatewayFile);
            }

            $config = [
                'services' => [
                    $serviceName => [
                        'image' => $image,
                        'volumes' => [
                            "./service/$serviceName:$volume",
                        ],
                    ],
                ],
            ];

            if (! empty($dependsOn)) {
                $config['services'][$serviceName]['depends_on'] = $dependsOn;
            }

            if (isset($restart)) {
                $config['services'][$serviceName]['restart'] = 'always';
            }

            if (isset($command)) {
                $config['services'][$serviceName]['command'] = $command;
            }

            $this->updateConfig($serviceName, $config);

            $io->success('Successfully updated microservice settings');

            return 0;
        } finally {
            $this->release();
        }
    }
}
