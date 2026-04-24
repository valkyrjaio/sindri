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

namespace Sindri\Tests\Unit\Generator\Ast\Http;

use PhpParser\Node\Scalar\String_;
use Sindri\Ast\Data\HttpParameterData;
use Sindri\Ast\Data\HttpRouteData;
use Sindri\Generator\Ast\Http\AstHttpDataFileGenerator;
use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Unit\Abstract\TestCase;

final class AstHttpDataFileGeneratorTest extends TestCase
{
    public function testGenerateClassContentsWithEmptyDataContainsDataClass(): void
    {
        $generator = new AstHttpDataFileGenerator();
        $contents  = $generator->generateClassContents([], []);

        self::assertStringContainsString('HttpRoutingData', $contents);
    }

    public function testGenerateClassContentsWithEmptyDataContainsRoutesKey(): void
    {
        $generator = new AstHttpDataFileGenerator();
        $contents  = $generator->generateClassContents([], []);

        self::assertStringContainsString('routes:', $contents);
    }

    public function testGenerateClassContentsWithRouteContainsRouteKey(): void
    {
        $generator  = new AstHttpDataFileGenerator();
        $routeData  = new HttpRouteData(path: '/test', name: 'test.route');
        $contents   = $generator->generateClassContents(
            ['test.route' => new String_('route-expr')],
            ['test.route' => $routeData],
        );

        self::assertStringContainsString("'test.route'", $contents);
    }

    public function testGenerateFileReturnsSuccessOnNewFile(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppHttpRoutingDataTest' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';

        $generator = new AstHttpDataFileGenerator();
        $status    = $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: [],
            routeData: [],
        );

        @unlink($filePath);

        self::assertSame(GenerateStatus::SUCCESS, $status);
    }

    public function testGenerateFileReturnsSkippedOnSameContent(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppHttpRoutingDataSkip' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';

        $generator = new AstHttpDataFileGenerator();
        $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: [],
            routeData: [],
        );

        $status = $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: [],
            routeData: [],
        );

        @unlink($filePath);

        self::assertSame(GenerateStatus::SKIPPED, $status);
    }

    public function testGenerateFileWithStaticRouteProducesPathsEntry(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppHttpRoutingDataStatic' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';

        $routeData = new HttpRouteData(
            path: '/static',
            name: 'static.route',
            requestMethods: ['Valkyrja\\Http\\Message\\Enum\\RequestMethod::GET'],
            isDynamic: false,
        );

        $generator = new AstHttpDataFileGenerator();
        $status    = $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: ['static.route' => new String_('route-expr')],
            routeData: ['static.route' => $routeData],
        );

        $contents = (string) file_get_contents($filePath);
        @unlink($filePath);

        self::assertSame(GenerateStatus::SUCCESS, $status);
        self::assertStringContainsString('/static', $contents);
    }

    public function testGenerateFileWithDynamicRouteProducesDynamicPathsEntry(): void
    {
        $directory = sys_get_temp_dir();
        $className = 'AppHttpRoutingDataDynamic' . uniqid();
        $filePath  = $directory . '/' . $className . '.php';

        $parameter = new HttpParameterData(name: 'id', regex: '[0-9]+');
        $routeData = new HttpRouteData(
            path: '/items/{id}',
            name: 'items.show',
            requestMethods: ['Valkyrja\\Http\\Message\\Enum\\RequestMethod::GET'],
            isDynamic: true,
            parameters: [$parameter],
        );

        $generator = new AstHttpDataFileGenerator();
        $status    = $generator->generateFile(
            directory: $directory,
            className: $className,
            namespace: 'App\\Data',
            routes: ['items.show' => new String_('route-expr')],
            routeData: ['items.show' => $routeData],
        );

        $contents = (string) file_get_contents($filePath);
        @unlink($filePath);

        self::assertSame(GenerateStatus::SUCCESS, $status);
        self::assertStringContainsString('/items/{id}', $contents);
    }
}