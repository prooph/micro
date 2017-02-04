<?php

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
