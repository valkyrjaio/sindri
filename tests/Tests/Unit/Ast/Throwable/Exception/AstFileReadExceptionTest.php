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

namespace Sindri\Tests\Unit\Ast\Throwable\Exception;

use Sindri\Ast\Throwable\Contract\AstThrowable;
use Sindri\Ast\Throwable\Exception\Abstract\AstRuntimeException;
use Sindri\Ast\Throwable\Exception\AstFileReadException;
use Sindri\Tests\Unit\Abstract\TestCase;

final class AstFileReadExceptionTest extends TestCase
{
    public function testExtendsAstRuntimeException(): void
    {
        self::assertInstanceOf(AstRuntimeException::class, new AstFileReadException(__FUNCTION__));
    }

    public function testImplementsAstThrowable(): void
    {
        self::assertInstanceOf(AstThrowable::class, new AstFileReadException(__FUNCTION__));
    }

    public function testMessageIsPreserved(): void
    {
        $exception = new AstFileReadException(__FUNCTION__);

        self::assertSame(__FUNCTION__, $exception->getMessage());
    }

    public function testCanBeThrown(): void
    {
        $this->expectException(AstFileReadException::class);
        $this->expectExceptionMessage(__FUNCTION__);

        throw new AstFileReadException(__FUNCTION__);
    }
}