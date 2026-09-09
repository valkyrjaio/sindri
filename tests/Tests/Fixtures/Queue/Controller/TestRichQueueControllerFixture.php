<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Fixtures\Queue\Controller;

use Sindri\Tests\Fixtures\Queue\Middleware\TestQueueMiddlewareFixture;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Queue\Message\Enum\JobResult;
use Valkyrja\Queue\Routing\Attribute\Route;
use Valkyrja\Queue\Routing\Attribute\Route\Middleware;
use Valkyrja\Queue\Routing\Attribute\Route\Name;
use Valkyrja\Queue\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Queue\Routing\Data\Contract\RouteContract;

final class TestRichQueueControllerFixture
{
    public static function handle(ContainerContract $container, RouteContract $route): JobResult
    {
        return JobResult::ACK;
    }

    #[Route(name: 'ChargeCard', description: 'Charge the card')]
    #[Name('ChargeCard.v2')]
    #[RouteHandler([self::class, 'handle'])]
    #[Middleware(TestQueueMiddlewareFixture::class)]
    // An empty name is skipped rather than classified, so the reader must
    // tolerate one without emitting an unusable middleware entry
    /** @phpstan-ignore-next-line */
    #[Middleware('')]
    public function chargeCard(): JobResult
    {
        return JobResult::ACK;
    }

    #[Route(name: '', description: 'Missing a name so it is skipped')]
    public function unnamed(): JobResult
    {
        return JobResult::ACK;
    }
}
