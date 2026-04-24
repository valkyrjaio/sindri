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

namespace Sindri\Tests\Unit\Ast\Throwable\Exception\Abstract;

use Sindri\Ast\Throwable\Contract\AstThrowable;
use Sindri\Ast\Throwable\Exception\Abstract\AstInvalidArgumentException;
use Sindri\Tests\Unit\Abstract\TestCase;
use Sindri\Throwable\Exception\Abstract\SindriInvalidArgumentException;

final class AstInvalidArgumentExceptionTest extends TestCase
{
    public function testExtendsSindriInvalidArgumentException(): void
    {
        self::assertIsA(SindriInvalidArgumentException::class, AstInvalidArgumentException::class);
    }

    public function testImplementsAstThrowable(): void
    {
        self::assertIsA(AstThrowable::class, AstInvalidArgumentException::class);
    }
}
