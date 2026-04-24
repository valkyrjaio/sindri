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

namespace Sindri\Tests\Classes\Http\Provider;

use Override;
use Valkyrja\Http\Routing\Provider\Contract\HttpRouteProviderContract;

/**
 * A route provider that references a non-existent controller class.
 * Used to exercise the "controller class file not found" branch in GenerateDataFromAst.
 */
final class TestMissingControllerProviderClass implements HttpRouteProviderContract
{
    #[Override]
    public static function getControllerClasses(): array
    {
        /** @phpstan-ignore class.notFound */
        return [NonExistentControllerClass::class];
    }

    #[Override]
    public static function getRoutes(): array
    {
        return [];
    }
}