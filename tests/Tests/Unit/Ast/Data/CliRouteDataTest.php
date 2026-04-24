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

use Sindri\Ast\Data\CliRouteData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class CliRouteDataTest extends TestCase
{
    public function testConstructorStoresNameAndDescription(): void
    {
        $data = new CliRouteData(name: 'route', description: 'desc');

        self::assertSame('route', $data->name);
        self::assertSame('desc', $data->description);
    }

    public function testConstructorDefaultsHandlerToNull(): void
    {
        $data = new CliRouteData(name: 'route', description: 'desc');

        self::assertNull($data->handler);
    }

    public function testConstructorDefaultsHelpTextToNull(): void
    {
        $data = new CliRouteData(name: 'route', description: 'desc');

        self::assertNull($data->helpText);
    }

    public function testConstructorDefaultsMiddlewareArraysToEmpty(): void
    {
        $data = new CliRouteData(name: 'route', description: 'desc');

        self::assertSame([], $data->routeMatchedMiddleware);
        self::assertSame([], $data->routeDispatchedMiddleware);
        self::assertSame([], $data->throwableCaughtMiddleware);
        self::assertSame([], $data->exitedMiddleware);
    }

    public function testConstructorDefaultsArgumentsAndOptionsToEmpty(): void
    {
        $data = new CliRouteData(name: 'route', description: 'desc');

        self::assertSame([], $data->arguments);
        self::assertSame([], $data->options);
    }
}