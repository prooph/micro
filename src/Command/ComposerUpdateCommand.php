<?php declare(strict_types = 1);

namespace Prooph\Micro\Command;

final class ComposerUpdateCommand extends AbstractComposerCommand
{
    protected function getComposerCommand(): string
    {
        return 'update';
    }

    protected function configure()
    {
        $this
            ->setName('micro:composer:update')
            ->setDescription('Updates composer dependencies for services')
        ;

        parent::configure();
    }
}
