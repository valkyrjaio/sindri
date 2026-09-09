<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Fixtures\Queue\Middleware;

use Override;
use Throwable;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Message\Job\Contract\JobContract;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ResultSettledHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteDispatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\RouteMatchedHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\SettlingResultHandlerContract;
use Valkyrja\Queue\Middleware\Handler\Contract\ThrowableCaughtHandlerContract;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

/**
 * Implements every queue middleware stage, so the reader must classify it into
 * all five lists from a single attribute.
 */
final class TestQueueMiddlewareFixture implements RouteMatchedMiddlewareContract, RouteDispatchedMiddlewareContract, ThrowableCaughtMiddlewareContract, SettlingResultMiddlewareContract, ResultSettledMiddlewareContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function routeMatched(JobContract $job, RouteContract $route, RouteMatchedHandlerContract $handler): RouteContract|JobResult
    {
        return $handler->routeMatched($job, $route);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routeDispatched(JobContract $job, JobResult $result, RouteContract $route, RouteDispatchedHandlerContract $handler): JobResult
    {
        return $handler->routeDispatched($job, $result, $route);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function throwableCaught(JobContract $job, JobResult $result, Throwable $throwable, ThrowableCaughtHandlerContract $handler): JobResult
    {
        return $handler->throwableCaught($job, $result, $throwable);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function settlingResult(JobContract $job, JobResult $result, SettlingResultHandlerContract $handler): JobResult
    {
        return $handler->settlingResult($job, $result);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function resultSettled(JobContract $job, JobResult $result, ResultSettledHandlerContract $handler): void
    {
        $handler->resultSettled($job, $result);
    }
}
