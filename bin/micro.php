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

namespace Prooph\Micro;

use Symfony\Component\Console\Application;

chdir(dirname(__DIR__));

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    include_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
    include_once __DIR__ . '/../../../autoload.php';
} else {
    throw new \RuntimeException('Error: vendor/autoload.php could not be found. Did you run php composer.phar install?');
}

$application = new Application('Prooph-Micro CLI');
$application->add(new Command\SetupCommand());
$application->add(new Command\CreateMySqlCommand());
$application->add(new Command\CreatePostgresCommand());
$application->add(new Command\CreatePhpServiceCommand());
$application->add(new Command\ComposerInstallCommand());
$application->add(new Command\ComposerUpdateCommand());
$application->run();
