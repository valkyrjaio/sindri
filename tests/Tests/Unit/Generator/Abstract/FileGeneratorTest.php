<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sindri\Tests\Unit\Generator\Abstract;

use Override;
use RuntimeException;
use Sindri\Generator\Abstract\FileGenerator;
use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Unit\Abstract\TestCase;

final class FileGeneratorTest extends TestCase
{
    public function testGenerateFile(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'FileGeneratorTest.testGenerateFile';
        $filePath  = $directory . '/' . $className . '.php';
        $generator = new class($directory, $className) extends FileGenerator {
            #[Override]
            public function generateFileContents(): string
            {
                return FileGeneratorTest::class . 'testGenerateFile contents';
            }
        };
        $results   = $generator->generateFile();

        self::assertSame(GenerateStatus::SUCCESS, $results);
        self::assertSame($generator->generateFileContents(), @file_get_contents($filePath));

        $results = $generator->generateFile();

        self::assertSame(GenerateStatus::SKIPPED, $results);

        @unlink($filePath);
    }

    public function testGenerateFileFailure(): void
    {
        $generator = new class('/tmp', 'filepath') extends FileGenerator {
            #[Override]
            protected function filePutContents(string $data): int|false
            {
                return false;
            }

            #[Override]
            public function generateFileContents(): string
            {
                return '';
            }
        };
        $results   = $generator->generateFile();

        self::assertSame(GenerateStatus::FAILURE, $results);
    }

    public function testGenerateFileFailureDueToException(): void
    {
        $generator = new class('/tmp', 'filepath') extends FileGenerator {
            #[Override]
            protected function filePutContents(string $data): int|false
            {
                throw new RuntimeException('Exception to test with');
            }

            #[Override]
            public function generateFileContents(): string
            {
                return '';
            }
        };
        $results   = $generator->generateFile();

        self::assertSame(GenerateStatus::FAILURE, $results);
    }
}
