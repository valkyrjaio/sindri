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

namespace Sindri\Tests\Classes\Cli\Middleware;

use LogicException;
use Throwable;
use Valkyrja\Cli\Interaction\Input\Contract\InputContract;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Middleware\Contract\ExitedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ExitedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Cli\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;

class TestCliMiddlewareClass implements RouteMatchedMiddlewareContract, RouteDispatchedMiddlewareContract, ThrowableCaughtMiddlewareContract, ExitedMiddlewareContract
{
    public function routeMatched(
        InputContract $input,
        RouteContract $route,
        RouteMatchedHandlerContract $handler,
    ): RouteContract|OutputContract {
        throw new LogicException('unreachable');
    }

    public function routeDispatched(
        InputContract $input,
        OutputContract $output,
        RouteContract $route,
        RouteDispatchedHandlerContract $handler,
    ): OutputContract {
        throw new LogicException('unreachable');
    }

    public function throwableCaught(
        InputContract $input,
        OutputContract $output,
        Throwable $throwable,
        ThrowableCaughtHandlerContract $handler,
    ): OutputContract {
        throw new LogicException('unreachable');
    }

    public function exited(
        InputContract $input,
        OutputContract $output,
        ExitedHandlerContract $handler,
    ): void {
        throw new LogicException('unreachable');
    }
}
