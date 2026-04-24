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

namespace Sindri\Tests\Unit\Generator\Throwable\Contract;

use Sindri\Generator\Throwable\Contract\GeneratorThrowable;
use Sindri\Tests\Unit\Abstract\TestCase;
use Sindri\Throwable\Contract\SindriThrowable;

final class GeneratorThrowableTest extends TestCase
{
    public function testExtendsSindriThrowable(): void
    {
        self::assertIsA(SindriThrowable::class, GeneratorThrowable::class);
    }
}