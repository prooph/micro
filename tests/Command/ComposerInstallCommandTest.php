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

namespace ProophTest\Micro\Command;

use Prooph\Micro\Command\ComposerInstallCommand;

/**
 * @coversDefaultClass Prooph\Micro\Command\ComposerInstallCommand
 */
final class ComposerInstallCommandTest extends ComposerCommandTestCase
{
    protected function getComposerCommandClass(): string
    {
        return ComposerInstallCommand::class;
    }
}
