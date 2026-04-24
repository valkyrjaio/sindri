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

namespace Sindri\Tests\Unit\Generator\Throwable\Exception;

use Sindri\Generator\Throwable\Contract\GeneratorThrowable;
use Sindri\Generator\Throwable\Exception\Abstract\GeneratorRuntimeException;
use Sindri\Generator\Throwable\Exception\GeneratorUnreachableException;
use Sindri\Tests\Unit\Abstract\TestCase;

final class GeneratorUnreachableExceptionTest extends TestCase
{
    public function testExtendsGeneratorRuntimeException(): void
    {
        self::assertInstanceOf(GeneratorRuntimeException::class, new GeneratorUnreachableException(__FUNCTION__));
    }

    public function testImplementsGeneratorThrowable(): void
    {
        self::assertInstanceOf(GeneratorThrowable::class, new GeneratorUnreachableException(__FUNCTION__));
    }

    public function testMessageIsPreserved(): void
    {
        $exception = new GeneratorUnreachableException(__FUNCTION__);

        self::assertSame(__FUNCTION__, $exception->getMessage());
    }

    public function testCanBeThrown(): void
    {
        $this->expectException(GeneratorUnreachableException::class);
        $this->expectExceptionMessage(__FUNCTION__);

        throw new GeneratorUnreachableException(__FUNCTION__);
    }
}