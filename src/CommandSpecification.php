<?php

/**
 * This file is part of the prooph/micro.
 * (c) 2017-2020 prooph software GmbH <contact@prooph.de>
 * (c) 2017-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\Micro;

use Closure;
use Generator;
use Phunkie\Types\ImmList;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;

abstract class CommandSpecification
{
    protected object $command;
    protected Closure $handler;

    public function __construct(object $command, callable $handler)
    {
        $this->command = $command;
        $this->handler = Closure::fromCallable($handler);
    }

    public function handle(Closure $stateResolver): Generator
    {
        return ($this->handler)($stateResolver, $this->command);
    }

    /**
     * @param ImmList<ResolvedEvent> $events
     * @return mixed
     */
    public function reconstituteFromHistory(ImmList $events)
    {
        return $this->apply($this->initialState(), $events->map(fn ($e) => $this->mapToEvent($e)));
    }

    public function expectedVersion(): int
    {
        return ExpectedVersion::ANY;
    }

    abstract public function enrich(object $event, callable $enrich): object;

    abstract public function mapToEventData(object $event): EventData;

    abstract public function mapToEvent(ResolvedEvent $resolvedEvent): object;

    /** @return mixed */
    abstract public function initialState();

    abstract public function streamName(): string;

    /**
     * @param mixed $initialState
     * @param ImmList $events
     * @return mixed
     */
    abstract public function apply($initialState, ImmList $events);
}
