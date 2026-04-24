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

namespace Sindri\Tests\Unit\Throwable\Exception\Abstract;

use Sindri\Tests\Unit\Abstract\TestCase;
use Sindri\Throwable\Contract\SindriThrowable;
use Sindri\Throwable\Exception\Abstract\SindriInvalidArgumentException;
use Valkyrja\Throwable\Exception\Abstract\ValkyrjaInvalidArgumentException;

final class SindriInvalidArgumentExceptionTest extends TestCase
{
    public function testExtendsValkyrjaInvalidArgumentException(): void
    {
        self::assertIsA(ValkyrjaInvalidArgumentException::class, SindriInvalidArgumentException::class);
    }

    public function testImplementsSindriThrowable(): void
    {
        self::assertIsA(SindriThrowable::class, SindriInvalidArgumentException::class);
    }
}