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

namespace Prooph\MicroExample\Infrastructure;

use Prooph\Common\Messaging\Message;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AbstractAggregateDefiniton;
use Prooph\MicroExample\Model\User;

final class UserAggregateDefinition extends AbstractAggregateDefiniton
{
    public function aggregateType(): string
    {
        return 'user';
    }

    public function streamName(string $aggregateId): StreamName
    {
        return new StreamName('user_stream'); // add aggregate id for one stream per aggregate
    }

    public function apply(array $state, Message ...$events): array
    {
        return User\apply($state, ...$events);
    }
}
