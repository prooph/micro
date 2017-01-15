<?php
declare(strict_types = 1);

namespace ProophExample\Micro\Model;

use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Metadata\Operator;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AggregateResult;
use Prooph\Micro\Fn;
use Prooph\Micro\FunctionalAggregate;
use Prooph\Micro\StreamMatcher;
use ProophExample\Micro\Model\Command\ChangeUserName;
use ProophExample\Micro\Model\Command\RegisterUser;
use ProophExample\Micro\Model\Event\UserNameWasChanged;
use ProophExample\Micro\Model\Event\UserWasRegistered;
use ProophExample\Micro\Model\Event\UserWasRegisteredWithDuplicateEmail;

final class User implements FunctionalAggregate
{
    public static function register(RegisterUser $command, array $state, UniqueEmailGuard $guard): AggregateResult
    {
        if ($guard->isUnique($command->email())) {
            $event = new UserWasRegistered($command->payload());
        } else {
            $event = new UserWasRegisteredWithDuplicateEmail($command->payload());
        }

        return self::applyAndBuildResult($state, [$event]);
    }

    public static function changeUserName(ChangeUserName $command, array $state): AggregateResult
    {
        Fn::assertTargetState($command, $state, self::identifierName());

        if(!mb_strlen($command->username()) > 3) {
            throw new \InvalidArgumentException('Username too short');
        }

        $event = new UserNameWasChanged($command->payload());

        return self::applyAndBuildResult($state, [$event]);
    }

    public static function identifierName(): string
    {
        return 'id';
    }

    public static function reconstituteState(Stream $events): array
    {
        $state = [];
        foreach ($events->streamEvents() as $event) $state = self::apply($state, $event);
        return $state;
    }

    public static function apply(array $state, Message $event): array
    {
        switch ($event->messageName()) {
            case UserWasRegistered::class:
                return array_merge($state, $event->payload(), ['activated' => true]);
            case UserWasRegisteredWithDuplicateEmail::class:
                return array_merge($state, $event->payload(), ['activated' => false, 'blocked_reason' => 'duplicate email']);
            case UserNameWasChanged::class:
                /** @var UserNameWasChanged $event */
                return array_merge($state, ['name' => $event->username()]);
        }
    }

    public static function streamMatcher(string $aggregateId): StreamMatcher
    {
        return new StreamMatcher(
            new StreamName('user_stream'),
            (new MetadataMatcher)->withMetadataMatch('_aggregate_id', Operator::EQUALS(), $aggregateId),
            new class($aggregateId) implements MetadataEnricher {

                private $id;

                public function __construct(string $id)
                {
                    $this->id = $id;
                }

                public function enrich(Message $message): Message
                {
                    return $message->withAddedMetadata('_aggregate_id', $this->id);
                }
            }
        );
    }

    protected static function applyAndBuildResult(array $state, array $events): AggregateResult
    {
        foreach ($events as $event) $state = self::apply($state, $event);
        return new AggregateResult($events, $state);
    }
}
