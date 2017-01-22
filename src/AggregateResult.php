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

use InvalidArgumentException;
use Prooph\Common\Messaging\Message;

final class AggregateResult
{
    private $raisedEvents;

    private $state;

    public function __construct(array $raisedEvents, array $state)
    {
        foreach ($raisedEvents as $event) {
            $this->assertEvent($event);
        }
        $this->raisedEvents = $raisedEvents;
        $this->state = $state;
    }

    /**
     * @return Message[]
     */
    public function raisedEvents(): array
    {
        return $this->raisedEvents;
    }

    public function state(): array
    {
        return $this->state;
    }

    private function assertEvent(Message $event): void
    {
        if ($event->messageType() !== Message::TYPE_EVENT) {
            throw new InvalidArgumentException('Message has to be of type event. Got ' . $event->messageType());
        }
    }
}
