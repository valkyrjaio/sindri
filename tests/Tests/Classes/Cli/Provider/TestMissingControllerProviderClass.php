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

namespace Sindri\Tests\Classes\Cli\Provider;

use Override;
use Valkyrja\Cli\Routing\Provider\Contract\CliRouteProviderContract;

/**
 * A route provider that references a non-existent controller class.
 * Used to exercise the "controller class file not found" branch in GenerateDataFromAst.
 */
final class TestMissingControllerProviderClass implements CliRouteProviderContract
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