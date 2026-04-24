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

use Sindri\Constant\SindriInfo;
use Sindri\Tests\Unit\Abstract\TestCase;

final class SindriInfoTest extends TestCase
{
    public function testVersionIsNonEmptyString(): void
    {
        self::assertNotSame('', SindriInfo::VERSION);
    }

    public function testVersionBuildDateTimeIsNonEmptyString(): void
    {
        self::assertNotSame('', SindriInfo::VERSION_BUILD_DATE_TIME);
    }
}