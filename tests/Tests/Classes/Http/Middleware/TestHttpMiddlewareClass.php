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

namespace Sindri\Tests\Classes\Http\Middleware;

use LogicException;
use Throwable;
use Valkyrja\Http\Message\Request\Contract\ServerRequestContract;
use Valkyrja\Http\Message\Response\Contract\ResponseContract;
use Valkyrja\Http\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\TerminatedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\SendingResponseHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\TerminatedHandlerContract;
use Valkyrja\Http\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Http\Routing\Data\Contract\RouteContract;

class TestHttpMiddlewareClass implements
    RouteMatchedMiddlewareContract,
    RouteDispatchedMiddlewareContract,
    ThrowableCaughtMiddlewareContract,
    SendingResponseMiddlewareContract,
    TerminatedMiddlewareContract
{
    public function routeMatched(
        ServerRequestContract $request,
        RouteContract $route,
        RouteMatchedHandlerContract $handler,
    ): RouteContract|ResponseContract {
        throw new LogicException('unreachable');
    }

    public function routeDispatched(
        ServerRequestContract $request,
        ResponseContract $response,
        RouteContract $route,
        RouteDispatchedHandlerContract $handler,
    ): ResponseContract {
        throw new LogicException('unreachable');
    }

    public function throwableCaught(
        ServerRequestContract $request,
        ResponseContract $response,
        Throwable $throwable,
        ThrowableCaughtHandlerContract $handler,
    ): ResponseContract {
        throw new LogicException('unreachable');
    }

    public function sendingResponse(
        ServerRequestContract $request,
        ResponseContract $response,
        SendingResponseHandlerContract $handler,
    ): ResponseContract {
        throw new LogicException('unreachable');
    }

    public function terminated(
        ServerRequestContract $request,
        ResponseContract $response,
        TerminatedHandlerContract $handler,
    ): void {
        throw new LogicException('unreachable');
    }
}