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

namespace Sindri\Tests\Unit\Throwable\Contract;

use Sindri\Tests\Unit\Abstract\TestCase;
use Sindri\Throwable\Contract\SindriThrowable;
use Valkyrja\Throwable\Contract\ValkyrjaThrowable;

final class SindriThrowableTest extends TestCase
{
    public function testExtendsValkyrjaThrowable(): void
    {
        self::assertIsA(ValkyrjaThrowable::class, SindriThrowable::class);
    }
}
