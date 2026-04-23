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

namespace Sindri\Tests\Classes\Provider;

use Override;
use Sindri\Tests\Classes\Provider\Sub\TestOtherServiceProviderClass;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Contract\ComponentProviderContract;

final class TestSecondComponentProviderClass implements ComponentProviderContract
{
    #[Override]
    public static function getComponentProviders(ApplicationContract $app): array
    {
        return [];
    }

    #[Override]
    public static function getContainerProviders(ApplicationContract $app): array
    {
        return [TestOtherServiceProviderClass::class];
    }

    #[Override]
    public static function getEventProviders(ApplicationContract $app): array
    {
        return [];
    }

    #[Override]
    public static function getCliProviders(ApplicationContract $app): array
    {
        return [];
    }

    #[Override]
    public static function getHttpProviders(ApplicationContract $app): array
    {
        return [];
    }
}
