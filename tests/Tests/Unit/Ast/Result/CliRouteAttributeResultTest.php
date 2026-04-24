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

namespace Sindri\Tests\Unit\Ast\Result;

use Sindri\Ast\Result\CliRouteAttributeResult;
use Sindri\Tests\Unit\Abstract\TestCase;

final class CliRouteAttributeResultTest extends TestCase
{
    public function testDefaultConstructorHasEmptyRoutesArray(): void
    {
        $result = new CliRouteAttributeResult();

        self::assertSame([], $result->routes);
    }
}