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

namespace Sindri\Tests\Unit\Generator\Throwable\Exception\Abstract;

use Sindri\Generator\Throwable\Contract\GeneratorThrowable;
use Sindri\Generator\Throwable\Exception\Abstract\GeneratorInvalidArgumentException;
use Sindri\Tests\Unit\Abstract\TestCase;
use Sindri\Throwable\Exception\Abstract\SindriInvalidArgumentException;

final class GeneratorInvalidArgumentExceptionTest extends TestCase
{
    public function testExtendsSindriInvalidArgumentException(): void
    {
        self::assertIsA(SindriInvalidArgumentException::class, GeneratorInvalidArgumentException::class);
    }

    public function testImplementsGeneratorThrowable(): void
    {
        self::assertIsA(GeneratorThrowable::class, GeneratorInvalidArgumentException::class);
    }
}