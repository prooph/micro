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

namespace Prooph\MicroExample\Infrastructure;

use Phunkie\Types\ImmList;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Json;
use Prooph\Micro\CommandSpecification;
use Prooph\MicroExample\Model\Event\UserNameChanged;
use Prooph\MicroExample\Model\Event\UserRegistered;
use Prooph\MicroExample\Model\Event\UserRegisteredWithDuplicateEmail;
use Prooph\MicroExample\Model\User;

final class UserSpecification extends CommandSpecification
{
    public function mapToEventData(object $event): EventData
    {
        return new EventData(
            EventId::generate(),
            $event->messageName(),
            true,
            Json::encode($event->payload()),
            Json::encode(['causation_name' => $this->command->messageName()])
        );
    }

    public function mapToEvent(ResolvedEvent $resolvedEvent): object
    {
        switch ($resolvedEvent->originalEvent()->eventType()) {
            case 'username-changed':
                return new UserNameChanged(Json::decode($resolvedEvent->originalEvent()->data()));
            case 'user-registered':
                return new UserRegistered(Json::decode($resolvedEvent->originalEvent()->data()));
            case 'user-registered-with-duplicate-email':
                return new UserRegisteredWithDuplicateEmail(Json::decode($resolvedEvent->originalEvent()->data()));
            default:
                throw new \UnexpectedValueException(
                    'Unknown event type ' . $resolvedEvent->originalEvent()->eventType() . ' returned'
                );
        }
    }

    public function initialState()
    {
        return [];
    }

    public function streamName(): string
    {
        return 'user-' . $this->command->payload()['id'];
    }

    public function apply($state, ImmList $events)
    {
        return User\apply($state, $events);
    }
}
