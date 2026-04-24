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

namespace Sindri\Tests\Unit\Generator\Abstract;

use Sindri\Generator\Abstract\AstFileGenerator;
use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Unit\Abstract\TestCase;

final class AstFileGeneratorTest extends TestCase
{
    public function testWriteFileReturnsSuccessForNewFile(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AstFileGeneratorTestNew' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';

        $generator = new class extends AstFileGenerator {
            public function write(string $directory, string $className, string $data): GenerateStatus
            {
                return $this->writeFile($directory, $className, $data);
            }
        };

        $status = $generator->write($directory, $className, '<?php // test content');

        @unlink($filePath);

        self::assertSame(GenerateStatus::SUCCESS, $status);
    }

    public function testWriteFileReturnsSkippedWhenContentUnchanged(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AstFileGeneratorTestSkip' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';
        $content   = '<?php // same content';

        $generator = new class extends AstFileGenerator {
            public function write(string $directory, string $className, string $data): GenerateStatus
            {
                return $this->writeFile($directory, $className, $data);
            }
        };

        // Write once
        $generator->write($directory, $className, $content);

        // Write again with same content — should be SKIPPED
        $status = $generator->write($directory, $className, $content);

        @unlink($filePath);

        self::assertSame(GenerateStatus::SKIPPED, $status);
    }

    public function testWriteFileReturnsFailureWhenDirectoryDoesNotExist(): void
    {
        $directory = sys_get_temp_dir() . '/nonexistent_dir_' . uniqid();
        $className = 'AstFileGeneratorTestFail';

        $generator = new class extends AstFileGenerator {
            public function write(string $directory, string $className, string $data): GenerateStatus
            {
                return $this->writeFile($directory, $className, $data);
            }
        };

        // Suppress the expected file_put_contents warning — the generator catches the
        // resulting Throwable internally and returns FAILURE, which is what we assert.
        $status = @$generator->write($directory, $className, '<?php // test');

        self::assertSame(GenerateStatus::FAILURE, $status);
    }
}