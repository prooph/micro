<?php

declare(strict_types=1);

namespace ProophExample\Micro\Script;

use DateTimeImmutable;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\InMemoryEventStore;
use Prooph\Micro\AggregateResult;
use Prooph\ServiceBus\Async\MessageProducer;
use ProophExample\Micro\Infrastructure\UserAggregateDefinition;
use ProophExample\Micro\Model\Command\ChangeUserName;
use ProophExample\Micro\Model\Command\RegisterUser;
use Ramsey\Uuid\Uuid;
use React\Promise\Deferred;

require __DIR__ . '/../vendor/autoload.php';
require 'Model/User.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$eventStore = new InMemoryEventStore();

$producer = function() {
    return new class() implements MessageProducer {
        public function __invoke(Message $message, Deferred $deferred = null): void
        {
        }
    };
};

$commandMap = [
    RegisterUser::class => [
        'handler' => function (array $state, Message $message) use (&$factories): AggregateResult {
            return \ProophExample\Micro\Model\User\registerUser($state, $message, $factories['emailGuard']());
        },
        'definition' => UserAggregateDefinition::class,
    ],
    ChangeUserName::class => [
        'handler' => '\ProophExample\Micro\Model\User\changeUserName',
        'definition' => UserAggregateDefinition::class,
    ],
];

$dispatch = \Prooph\Micro\Kernel\build($eventStore)($producer)($commandMap);

$command = new RegisterUser(['id' => '1', 'name' => 'Alex', 'email' => 'member@getprooph.org']);

$state = $dispatch($command);

echo get_class($state) . "\n";
echo "User was registered: \n";
echo json_encode($state()) . "\n\n";

$state = $dispatch(new ChangeUserName(['id' => '1', 'name' => 'Sascha']));

echo get_class($state) . "\n";
echo "Username changed: \n";
echo json_encode($state()) . "\n\n";

$state = $dispatch(new class implements Message {

    private $metadata = [];

    public function messageName(): string
    {
        return 'invalid message';
    }

    public function messageType(): string
    {
        return Message::TYPE_EVENT;
    }

    public function uuid(): Uuid
    {
        return Uuid::uuid4();
    }

    public function createdAt(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    public function payload(): array
    {
        return [];
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function withMetadata(array $metadata): Message
    {
        $message = clone $this;
        $message->metadata = $metadata;

        return $message;
    }

    public function withAddedMetadata(string $key, $value): Message
    {
        $message = clone $this;
        $message->metadata[$key] = $value;

        return $message;
    }
});

echo get_class($state) . "\n";
echo json_encode($state()) . "\n\n";
