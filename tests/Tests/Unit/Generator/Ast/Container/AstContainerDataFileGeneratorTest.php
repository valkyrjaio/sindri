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

namespace Sindri\Tests\Unit\Generator\Ast\Container;

use Sindri\Generator\Ast\Container\AstContainerDataFileGenerator;
use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Unit\Abstract\TestCase;

final class AstContainerDataFileGeneratorTest extends TestCase
{
    public function testGenerateClassContentsWithEmptyPublishersContainsDataClass(): void
    {
        $generator = new AstContainerDataFileGenerator();
        $contents  = $generator->generateClassContents([]);

        self::assertStringContainsString('ContainerData', $contents);
    }

    public function testGenerateClassContentsWithEmptyPublishersContainsDeferredCallback(): void
    {
        $generator = new AstContainerDataFileGenerator();
        $contents  = $generator->generateClassContents([]);

        self::assertStringContainsString('deferredCallback:', $contents);
    }

    public function testGenerateClassContentsWithPublishersContainsPublisherEntry(): void
    {
        $generator = new AstContainerDataFileGenerator();
        $contents  = $generator->generateClassContents([
            'SomeService' => ['SomeProvider', 'publishSome'],
        ]);

        self::assertStringContainsString('SomeService', $contents);
        self::assertStringContainsString('SomeProvider', $contents);
        self::assertStringContainsString('publishSome', $contents);
    }

    public function testGenerateFileReturnsSuccessOnNewFile(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppContainerDataTest' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';

        $generator = new AstContainerDataFileGenerator();
        $status    = $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            publishers: [],
        );

        @unlink($filePath);

        self::assertSame(GenerateStatus::SUCCESS, $status);
    }

    public function testGenerateFileReturnsSkippedOnSameContent(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppContainerDataSkip' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';

        $generator = new AstContainerDataFileGenerator();
        $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            publishers: [],
        );

        $status = $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            publishers: [],
        );

        @unlink($filePath);

        self::assertSame(GenerateStatus::SKIPPED, $status);
    }
}