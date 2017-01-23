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

use RomanPitak\Nginx\Config\Directive;
use RomanPitak\Nginx\Config\Scope;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
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
        if (! $this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        if (! file_exists($this->getRootDir() . '/docker-compose.yml')) {
            $output->writeln('docker-compose.yml does not exist. Run ./bin/micro micro:setup. Aborted.');

            return 0;
        }

        $currentConfig = Yaml::parse(file_get_contents($this->getRootDir() . '/docker-compose.yml'));

        $helper = $this->getHelper('question');

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

        $question = new Question('Add a volume (f.e. "./service/' . $serviceName .':/var/www"): ');
        $question->setValidator(function ($answer) {
            if (! is_string($answer) || strlen($answer) === 0 || false === strpos($answer, ':', 1)) {
                throw new \RuntimeException(
                    'Invalid service name'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $volumes[] = $helper->ask($input, $output, $question);

        while (1) {
            $question = new Question('Add a another volume (f.e. "./service/' . $serviceName .':/var/www"): ', null);
            $question->setValidator(function ($answer) {
                if (null !== $answer && (! is_string($answer) || false === strpos($answer, ':', 1))) {
                    throw new \RuntimeException(
                        'Invalid service name'
                    );
                }

                return $answer;
            });
            $question->setMaxAttempts(2);

            $volume = $helper->ask($input, $output, $question);

            if (null === $volume) {
                break;
            }

            $volumes[] = $volume;
        }

        $services = array_merge(['[NONE]'], array_keys($currentConfig['services']));
        $question = new ChoiceQuestion('On which services is the new service dependend? ', $services);
        $question->setMultiselect(true);

        $dependsOn = $helper->ask($input, $output, $question);

        if ($dependsOn === ['[NONE]']) {
            $dependsOn = [];
        }

        $useNginxConfig = false;

        if (in_array('nginx', $dependsOn)) {
            $useNginxConfig = true;
            $labels = $currentConfig['services']['nginx']['labels'];

            foreach ($labels as $label) {
                foreach ($label as $key => $value) {
                    if ('prooph-gateway-directory' === $key) {
                        $gatewayFile = $this->getRootDir() . '/' . $value . '/www.conf';
                        break 2;
                    }
                }
            }

            if (! isset($gatewayFile)) {
                throw new \RuntimeException('No gateway directory found in nginx service config!');
            }

            $output->writeln('Nginx configuration');
            upstream:
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

            $upStreams[] = $helper->ask($input, $output, $question);

            $question = new ConfirmationQuestion('Add more upstreams? (y/n) ', false);

            if ($helper->ask($input, $output, $question)) {
                goto upstream;
            }

            nginxlocation:
            $question = new Question('Add location (f.e. "location = /api/v1/user-$request_method"): ');
            $question->setValidator(function ($answer) {
                if (! is_string($answer) || ! preg_match('/^location /', $answer)) {
                    throw new \RuntimeException(
                        'Invalid location'
                    );
                }

                return $answer;
            });
            $question->setMaxAttempts(2);

            $locations[] = $helper->ask($input, $output, $question);

            $question = new ChoiceQuestion('Add fastcgi_pass: ', $upStreams);

            $fastcgiPasses[] = $helper->ask($input, $output, $question);

            $question = new Question('Add fastcgi_index (defaults to "index.php"): ', 'index.php');
            $fastcgiIndexes[] = $helper->ask($input, $output, $question);

            $question = new Question('Add fastcgi_param SCRIPT_FILENAME (defaults to "\'/var/www/public/index.php\': "): ', '\'/var/www/public/index.php\'');
            $scriptFileNames[] = $helper->ask($input, $output, $question);

            $question = new Question('Add fastcgi_param PATH_INFO (defaults to "\'/\'"): ', '\'/\'');
            $pathInfos[] = $helper->ask($input, $output, $question);

            $question = new Question('Add fastcgi_param SCRIPT_NAME (defaults to "\'/index.php\'"): ', '\'/index.php\'');
            $scriptNames[] = $helper->ask($input, $output, $question);

            $moreNginxConfig = new ConfirmationQuestion('Do you want to add another nginx location? (y/n): ', false);

            if ($helper->ask($input, $output, $moreNginxConfig)) {
                goto nginxlocation;
            }
        } else {
            $question = new Question('Enter the start command (f.e. "php run.php"): ', '');
            $question->setValidator(function ($answer) {
                if (! is_string($answer) || strlen($answer) === 0) {
                    throw new \RuntimeException(
                        'Invalid command'
                    );
                }
            });
            $question->setMaxAttempts(2);

            $command = $helper->ask($input, $output, $question);

            $question = new ConfirmationQuestion('Always restart container if it exists? (y/n): ');
            $restart = $helper->ask($input, $output, $question);
        }

        $volumesString = '';
        foreach ($it = new \CachingIterator(new \ArrayIterator($volumes), \CachingIterator::FULL_CACHE) as $volume) {
            $volumesString .= "  - $volume";
            if ($it->hasNext()) {
                $volumesString .= "\n";
            }
        }

        $dependsOnString = '';
        foreach ($it = new \CachingIterator(new \ArrayIterator($dependsOn), \CachingIterator::FULL_CACHE) as $dependend) {
            $dependsOnString .= "  - $dependend\n";
            if ($it->hasNext()) {
                $volumesString .= "\n";
            }
        }

        if ($useNginxConfig) {
            $nginxConfig = Scope::fromFile($gatewayFile);

            foreach ($upStreams as $upStream) {
                $nginxConfig
                    ->addDirective(Directive::create('upstream', $upStream)
                        ->setChildScope(Scope::create()
                            ->addDirective(Directive::create('server', "$serviceName:9000;"))
                        )
                    );
            }

            while ($location = array_shift($locations)) {
                $fastcgiPass = array_shift($fastcgiPasses);
                $fastcgiIndex = array_shift($fastcgiIndexes);
                $scriptFileName = array_shift($scriptFileNames);
                $pathInfo = array_shift($pathInfos);
                $scriptName = array_shift($scriptNames);

                $nginxConfig
                    ->addDirective(Directive::create('server')
                        ->setChildScope(Scope::create()
                            ->addDirective(Directive::create($location)
                                ->setChildScope(Scope::create()
                                    ->addDirective(Directive::create('fastcgi_split_path_info', '^(.+\.php)(/.+)$'))
                                    ->addDirective(Directive::create('fastcgi_pass', $fastcgiPass))
                                    ->addDirective(Directive::create('fastcgi_index', $fastcgiIndex))
                                    ->addDirective(Directive::create('include', 'fastcgi_params'))
                                    ->addDirective(Directive::create('fastcgi_param', 'SCRIPT_FILENAME ' . $scriptFileName))
                                    ->addDirective(Directive::create('fastcgi_param', 'PATH_INFO ' . $pathInfo))
                                    ->addDirective(Directive::create('fastcgi_param', 'SCRIPT_NAME ' . $scriptName))
                                )
                            )
                        )
                    );
            }

            file_put_contents($gatewayFile, $nginxConfig->prettyPrint(-1));
        }

        $config = [
            'services' => [
                $serviceName => [
                    'image' => $image,
                    'volumes' => $volumes,
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

        $output->writeln('Successfully updated microservice settings');

        $this->release();
    }
}
