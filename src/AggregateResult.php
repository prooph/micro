<?php
declare(strict_types = 1);

namespace Prooph\Micro;

use Prooph\Common\Messaging\Message;

final class AggregateResult
{
    private $raisedEvents;

    private $state;

    public function __construct(array $raisedEvens, array $state)
    {
        foreach ($raisedEvens as $event) self::assertEvent($event);
        $this->raisedEvents = $raisedEvens;
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

    private static function assertEvent(Message $event)
    {
        if (!$event->messageType() === Message::TYPE_EVENT) {
            throw new \InvalidArgumentException('Message has to be of type event. Got ' . $event->messageType());
        }
    }
}
