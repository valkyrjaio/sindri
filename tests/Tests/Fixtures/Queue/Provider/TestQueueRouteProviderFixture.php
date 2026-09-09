<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Fixtures\Queue\Provider;

use Override;
use Sindri\Tests\Fixtures\Queue\Controller\TestQueueControllerFixture;
use Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract;

final class TestQueueRouteProviderFixture implements QueueRouteProviderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function getControllerClasses(): array
    {
        return [
            TestQueueControllerFixture::class,
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getRoutes(): array
    {
        return [];
    }
}
