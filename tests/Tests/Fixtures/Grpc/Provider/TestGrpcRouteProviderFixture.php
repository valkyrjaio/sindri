<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Fixtures\Grpc\Provider;

use Override;
use Sindri\Tests\Fixtures\Grpc\Controller\TestGrpcControllerFixture;
use Valkyrja\Grpc\Routing\Provider\Contract\GrpcRouteProviderContract;

final class TestGrpcRouteProviderFixture implements GrpcRouteProviderContract
{
    #[Override]
    public static function getControllerClasses(): array
    {
        return [TestGrpcControllerFixture::class];
    }

    #[Override]
    public static function getRoutes(): array
    {
        return [];
    }
}
