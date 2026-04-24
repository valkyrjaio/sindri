<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sindri\Tests\Unit\Ast\Data;

use Sindri\Ast\Data\ListenerData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ListenerDataTest extends TestCase
{
    public function testConstructorStoresEventId(): void
    {
        $data = new ListenerData(eventId: 'EventClass', name: 'listener-name');

        self::assertSame('EventClass', $data->eventId);
    }

    public function testConstructorStoresName(): void
    {
        $data = new ListenerData(eventId: 'EventClass', name: 'listener-name');

        self::assertSame('listener-name', $data->name);
    }

    public function testConstructorDefaultsHandlerToNull(): void
    {
        $data = new ListenerData(eventId: 'EventClass', name: 'listener-name');

        self::assertNull($data->handler);
    }
}
