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

namespace Sindri\Tests\Unit\Constant;

use Sindri\Constant\CommandName;
use Sindri\Tests\Unit\Abstract\TestCase;

final class CommandNameTest extends TestCase
{
    public function testDataGenerateConstant(): void
    {
        self::assertSame('data:generate', CommandName::DATA_GENERATE);
    }
}
