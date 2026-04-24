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

namespace Sindri\Tests\Unit\Generator\Enum;

use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Unit\Abstract\TestCase;

final class GenerateStatusTest extends TestCase
{
    public function testSuccessCaseExists(): void
    {
        self::assertSame(GenerateStatus::SUCCESS, GenerateStatus::SUCCESS);
    }

    public function testFailureCaseExists(): void
    {
        self::assertSame(GenerateStatus::FAILURE, GenerateStatus::FAILURE);
    }

    public function testSkippedCaseExists(): void
    {
        self::assertSame(GenerateStatus::SKIPPED, GenerateStatus::SKIPPED);
    }

    public function testThreeCasesExist(): void
    {
        self::assertCount(3, GenerateStatus::cases());
    }
}