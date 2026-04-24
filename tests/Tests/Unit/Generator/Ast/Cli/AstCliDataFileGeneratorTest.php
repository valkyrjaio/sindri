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

namespace Sindri\Tests\Unit\Generator\Ast\Cli;

use PhpParser\Node\Scalar\String_;
use Sindri\Generator\Ast\Cli\AstCliDataFileGenerator;
use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Unit\Abstract\TestCase;

final class AstCliDataFileGeneratorTest extends TestCase
{
    public function testGenerateClassContentsWithEmptyRoutesContainsDataClass(): void
    {
        $generator = new AstCliDataFileGenerator();
        $contents  = $generator->generateClassContents([]);

        self::assertStringContainsString('Sindri\Cli\Data\CliRoutingData', $contents);
    }

    public function testGenerateClassContentsWithEmptyRoutesContainsEmptyRoutesArray(): void
    {
        $generator = new AstCliDataFileGenerator();
        $contents  = $generator->generateClassContents([]);

        self::assertStringContainsString('routes:', $contents);
    }

    public function testGenerateClassContentsWithRouteContainsRouteKey(): void
    {
        $generator = new AstCliDataFileGenerator();
        $contents  = $generator->generateClassContents([
            'test:route' => new String_('test-route-expr'),
        ]);

        self::assertStringContainsString("'test:route'", $contents);
    }

    public function testGenerateFileReturnsSuccessOnNewFile(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppCliRoutingDataTest' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';

        $generator = new AstCliDataFileGenerator();
        $status    = $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: [],
        );

        @unlink($filePath);

        self::assertSame(GenerateStatus::SUCCESS, $status);
    }

    public function testGenerateFileReturnsSkippedOnSameContent(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppCliRoutingDataSkip' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';

        $generator = new AstCliDataFileGenerator();
        $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: [],
        );

        $status = $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: [],
        );

        @unlink($filePath);

        self::assertSame(GenerateStatus::SKIPPED, $status);
    }
}
