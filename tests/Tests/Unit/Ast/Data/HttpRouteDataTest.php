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

use Sindri\Ast\Data\HttpRouteData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class HttpRouteDataTest extends TestCase
{
    public function testConstructorStoresPathAndName(): void
    {
        $data = new HttpRouteData(path: '/test', name: 'test.route');

        self::assertSame('/test', $data->path);
        self::assertSame('test.route', $data->name);
    }

    public function testConstructorDefaultsHandlerToNull(): void
    {
        $data = new HttpRouteData(path: '/test', name: 'test.route');

        self::assertNull($data->handler);
    }

    public function testConstructorDefaultsRequestMethodsToEmpty(): void
    {
        $data = new HttpRouteData(path: '/test', name: 'test.route');

        self::assertSame([], $data->requestMethods);
    }

    public function testConstructorDefaultsMiddlewareArraysToEmpty(): void
    {
        $data = new HttpRouteData(path: '/test', name: 'test.route');

        self::assertSame([], $data->routeMatchedMiddleware);
        self::assertSame([], $data->routeDispatchedMiddleware);
        self::assertSame([], $data->throwableCaughtMiddleware);
        self::assertSame([], $data->sendingResponseMiddleware);
        self::assertSame([], $data->terminatedMiddleware);
    }

    public function testConstructorDefaultsStructsToNull(): void
    {
        $data = new HttpRouteData(path: '/test', name: 'test.route');

        self::assertNull($data->requestStruct);
        self::assertNull($data->responseStruct);
    }

    public function testConstructorDefaultsIsDynamicToFalse(): void
    {
        $data = new HttpRouteData(path: '/test', name: 'test.route');

        self::assertFalse($data->isDynamic);
    }

    public function testConstructorDefaultsParametersToEmpty(): void
    {
        $data = new HttpRouteData(path: '/test', name: 'test.route');

        self::assertSame([], $data->parameters);
    }
}
