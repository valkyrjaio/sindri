<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Unit\Ast\Data;

use Sindri\Ast\Data\HandlerData;
use Sindri\Ast\Data\QueueRouteData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class QueueRouteDataTest extends TestCase
{
    public function testDefaults(): void
    {
        $data = new QueueRouteData(name: 'SendWelcomeEmail', description: 'Send the welcome email');

        self::assertSame('SendWelcomeEmail', $data->name);
        self::assertSame('Send the welcome email', $data->description);
        self::assertNull($data->handler);
        self::assertSame([], $data->routeMatchedMiddleware);
        self::assertSame([], $data->routeDispatchedMiddleware);
        self::assertSame([], $data->throwableCaughtMiddleware);
        self::assertSame([], $data->settlingResultMiddleware);
        self::assertSame([], $data->resultSettledMiddleware);
    }

    public function testConstructor(): void
    {
        $handler = new HandlerData(class: 'App\\Job', method: 'handle');

        $data = new QueueRouteData(
            name: 'ChargeCard',
            description: 'Charge the card',
            handler: $handler,
            routeMatchedMiddleware: ['A'],
            routeDispatchedMiddleware: ['B'],
            throwableCaughtMiddleware: ['C'],
            settlingResultMiddleware: ['D'],
            resultSettledMiddleware: ['E'],
        );

        self::assertSame($handler, $data->handler);
        self::assertSame(['A'], $data->routeMatchedMiddleware);
        self::assertSame(['B'], $data->routeDispatchedMiddleware);
        self::assertSame(['C'], $data->throwableCaughtMiddleware);
        self::assertSame(['D'], $data->settlingResultMiddleware);
        self::assertSame(['E'], $data->resultSettledMiddleware);
    }
}
