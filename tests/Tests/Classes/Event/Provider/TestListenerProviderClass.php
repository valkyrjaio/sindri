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

namespace Sindri\Tests\Classes\Event\Provider;

use Override;
use Sindri\Tests\Classes\Event\TestListenerClass;
use Valkyrja\Event\Provider\Contract\ListenerProviderContract;

final class TestListenerProviderClass implements ListenerProviderContract
{
    #[Override]
    public static function getListenerClasses(): array
    {
        return [TestListenerClass::class];
    }

    #[Override]
    public static function getListeners(): array
    {
        return [];
    }
}