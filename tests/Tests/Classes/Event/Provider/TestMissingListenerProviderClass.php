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
use Valkyrja\Event\Provider\Contract\ListenerProviderContract;

/**
 * A listener provider that references a non-existent listener class.
 * Used to exercise the "listener class file not found" branch in GenerateDataFromAst.
 */
final class TestMissingListenerProviderClass implements ListenerProviderContract
{
    #[Override]
    public static function getListenerClasses(): array
    {
        /** @phpstan-ignore class.notFound */
        return [NonExistentListenerClass::class];
    }

    #[Override]
    public static function getListeners(): array
    {
        return [];
    }
}