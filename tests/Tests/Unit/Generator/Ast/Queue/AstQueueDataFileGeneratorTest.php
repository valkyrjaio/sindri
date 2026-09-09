<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Unit\Generator\Ast\Queue;

use PhpParser\Node\Scalar\String_;
use Sindri\Generator\Ast\Queue\AstQueueDataFileGenerator;
use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Unit\Abstract\TestCase;

use function file_get_contents;
use function sys_get_temp_dir;
use function unlink;

final class AstQueueDataFileGeneratorTest extends TestCase
{
    public function testGenerateClassContentsWithEmptyRoutesContainsTheDataClass(): void
    {
        $contents = new AstQueueDataFileGenerator()->generateClassContents([]);

        self::assertStringContainsString('Sindri\Queue\Data\QueueRoutingData', $contents);
        self::assertStringContainsString('routes:', $contents);
    }

    public function testGenerateClassContentsWithARouteContainsTheJobNameKey(): void
    {
        $contents = new AstQueueDataFileGenerator()->generateClassContents([
            'SendWelcomeEmail' => new String_('route-expr'),
        ]);

        self::assertStringContainsString("'SendWelcomeEmail'", $contents);
    }

    public function testGenerateClassContentsWithAConstantKeyOutputsAConstantReference(): void
    {
        $contents = new AstQueueDataFileGenerator()->generateClassContents([
            'App\\Queue\\Constant\\JobName::SEND_WELCOME_EMAIL' => new String_('route-expr'),
        ]);

        self::assertStringContainsString('\\App\\Queue\\Constant\\JobName::SEND_WELCOME_EMAIL', $contents);
        self::assertStringNotContainsString("'App\\Queue\\Constant\\JobName::SEND_WELCOME_EMAIL'", $contents);
    }

    public function testGenerateFileWritesAValidDataClass(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppQueueRoutingDataTestGenerated';
        $path      = $directory . '/' . $className . '.php';

        $status = new AstQueueDataFileGenerator()->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'Sindri\\Tests\\Generated',
            routes: ['SendWelcomeEmail' => new String_('route-expr')],
        );

        $contents = (string) file_get_contents($path);

        unlink($path);

        self::assertSame(GenerateStatus::SUCCESS, $status);
        self::assertStringContainsString('namespace Sindri\\Tests\\Generated;', $contents);
        self::assertStringContainsString('use Valkyrja\\Queue\\Routing\\Data\\QueueRoutingData;', $contents);
        self::assertStringContainsString("final readonly class $className extends QueueRoutingData", $contents);
        // Routes are lazily built, matching the runtime collection's own shape
        self::assertStringContainsString('static fn (): \\Valkyrja\\Queue\\Routing\\Data\\Contract\\RouteContract =>', $contents);
    }

    public function testGenerateFileWithNoRoutesStillWritesTheClass(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppQueueRoutingDataEmptyGenerated';
        $path      = $directory . '/' . $className . '.php';

        $status = new AstQueueDataFileGenerator()->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'Sindri\\Tests\\Generated',
            routes: [],
        );

        $contents = (string) file_get_contents($path);

        unlink($path);

        self::assertSame(GenerateStatus::SUCCESS, $status);
        self::assertStringContainsString('routes: [', $contents);
    }
}
