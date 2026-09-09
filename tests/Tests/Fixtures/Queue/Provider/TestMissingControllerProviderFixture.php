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
use Valkyrja\Queue\Routing\Provider\Contract\QueueRouteProviderContract;

/**
 * A queue route provider that references a non-existent handler class.
 * Used to exercise the "controller class file not found" branch in GenerateDataFromAst.
 */
final class TestMissingControllerProviderFixture implements QueueRouteProviderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function getControllerClasses(): array
    {
        /* @phpstan-ignore class.notFound */
        return [NonExistentQueueHandlerClass::class];
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
