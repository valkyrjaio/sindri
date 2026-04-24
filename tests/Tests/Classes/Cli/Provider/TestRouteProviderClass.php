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
use Sindri\Tests\Classes\Cli\Controller\TestCliControllerClass;
use Valkyrja\Cli\Routing\Provider\Contract\CliRouteProviderContract;

final class TestRouteProviderClass implements CliRouteProviderContract
{
    #[Override]
    public static function getControllerClasses(): array
    {
        return [TestCliControllerClass::class];
    }

    #[Override]
    public static function getRoutes(): array
    {
        return [];
    }
}
