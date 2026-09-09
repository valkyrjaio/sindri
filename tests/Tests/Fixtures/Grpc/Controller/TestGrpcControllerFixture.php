<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Fixtures\Grpc\Controller;

use Sindri\Tests\Fixtures\Grpc\Middleware\TestGrpcMiddlewareFixture;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Grpc\Message\Response\Contract\ServiceResponseContract;
use Valkyrja\Grpc\Message\Response\ServiceResponse;
use Valkyrja\Grpc\Routing\Attribute\Method;
use Valkyrja\Grpc\Routing\Attribute\Method\Middleware;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Contract\RouteContract;

#[Service(service: 'pkg.Greeter')]
final class TestGrpcControllerFixture
{
    #[Method(name: 'SayHello')]
    public static function sayHello(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('hello');
    }

    #[Method(name: 'Chat', clientStreaming: true, serverStreaming: true)]
    #[Middleware(name: TestGrpcMiddlewareFixture::class)]
    public static function chat(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('chat');
    }

    /**
     * Not attributed, so the scan must skip it.
     */
    public static function notAnRpc(ContainerContract $container, RouteContract $route): ServiceResponseContract
    {
        return ServiceResponse::ok('skipped');
    }
}
