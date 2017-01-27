<?php

declare(strict_types=1);

namespace Prooph\Micro\Command;

final class ComposerInstallCommand extends AbstractComposerCommand
{
    protected function getComposerCommand(): string
    {
        return 'install';
    }

    protected function configure()
    {
        $this
            ->setName('micro:composer:install')
            ->setDescription('Installs composer dependencies for services');

        parent::configure();
    }
}
