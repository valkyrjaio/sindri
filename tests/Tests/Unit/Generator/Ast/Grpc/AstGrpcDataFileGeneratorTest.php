<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Unit\Generator\Ast\Grpc;

use PhpParser\Node\Scalar\String_;
use Sindri\Generator\Ast\Grpc\AstGrpcDataFileGenerator;
use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Unit\Abstract\TestCase;
use Valkyrja\Grpc\Routing\Data\GrpcRoutingData;

use function file_get_contents;
use function sys_get_temp_dir;
use function unlink;

final class AstGrpcDataFileGeneratorTest extends TestCase
{
    public function testGenerateClassContentsWithEmptyRoutesContainsDataClass(): void
    {
        $contents = new AstGrpcDataFileGenerator()->generateClassContents([]);

        self::assertStringContainsString(GrpcRoutingData::class, $contents);
        self::assertStringContainsString('routes:', $contents);
    }

    public function testGenerateClassContentsKeysRoutesByTheFullyQualifiedMethod(): void
    {
        $contents = new AstGrpcDataFileGenerator()->generateClassContents([
            '/pkg.Greeter/SayHello' => new String_('route-expr'),
        ]);

        self::assertStringContainsString("'/pkg.Greeter/SayHello'", $contents);
    }

    public function testGenerateClassContentsWrapsEachRouteInASupplier(): void
    {
        $contents = new AstGrpcDataFileGenerator()->generateClassContents([
            '/pkg.Greeter/SayHello' => new String_('route-expr'),
        ]);

        self::assertStringContainsString('static fn (): \Valkyrja\Grpc\Routing\Data\Contract\RouteContract =>', $contents);
    }

    public function testGenerateFileReturnsSuccessOnNewFile(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppGrpcRoutingDataTest' . __FUNCTION__;
        $filePath  = $directory . '/' . $className . '.php';

        $status = new AstGrpcDataFileGenerator()->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: [],
        );

        @unlink($filePath);

        self::assertSame(GenerateStatus::SUCCESS, $status);
    }

    public function testGeneratedFileExtendsTheFrameworkRoutingData(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppGrpcRoutingDataTest' . __FUNCTION__;
        $filePath  = $directory . '/' . $className . '.php';

        new AstGrpcDataFileGenerator()->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: [
                '/pkg.Greeter/SayHello' => new String_('route-expr'),
            ],
        );

        $contents = file_get_contents($filePath);

        @unlink($filePath);

        self::assertNotFalse($contents);
        self::assertStringContainsString('namespace App\\Data;', $contents);
        self::assertStringContainsString('use Valkyrja\\Grpc\\Routing\\Data\\GrpcRoutingData;', $contents);
        self::assertStringContainsString("final readonly class $className extends GrpcRoutingData", $contents);
        self::assertStringContainsString("'/pkg.Greeter/SayHello'", $contents);
    }
}
