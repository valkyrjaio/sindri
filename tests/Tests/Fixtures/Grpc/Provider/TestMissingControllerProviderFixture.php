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
use Valkyrja\Grpc\Routing\Provider\Contract\GrpcRouteProviderContract;

/**
 * A route provider that references a non-existent controller class.
 * Used to exercise the "controller class file not found" branch in GenerateDataFromAst.
 */
final class TestMissingControllerProviderFixture implements GrpcRouteProviderContract
{
    #[Override]
    public static function getControllerClasses(): array
    {
        /* @phpstan-ignore class.notFound */
        return [NonExistentGrpcControllerClass::class];
    }

    #[Override]
    public static function getRoutes(): array
    {
        return [];
    }
}
