<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Unit\Ast;

use PhpParser\PrettyPrinter\Standard;
use Sindri\Ast\GrpcRouteAttributeReader;
use Sindri\Tests\Fixtures\Grpc\Controller\TestGrpcControllerFixture;
use Sindri\Tests\Fixtures\Grpc\Middleware\TestGrpcMiddlewareFixture;
use Sindri\Tests\Unit\Abstract\TestCase;

use function file_put_contents;
use function realpath;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class GrpcRouteAttributeReaderTest extends TestCase
{
    private static string $fixtureFile;

    private static string $unattributedFixtureFile;

    private static function print(mixed $expr): string
    {
        return new Standard()->prettyPrintExpr($expr);
    }

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Fixtures/Grpc/Controller/TestGrpcControllerFixture.php');

        self::$fixtureFile = $path;

        /** @var non-empty-string $unattributedPath */
        $unattributedPath = realpath(__DIR__ . '/../../Fixtures/Grpc/Controller/TestUnattributedGrpcControllerFixture.php');

        self::$unattributedFixtureFile = $unattributedPath;
    }

    public function testKeysRoutesByTheFullyQualifiedMethod(): void
    {
        $result = new GrpcRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertArrayHasKey('/pkg.Greeter/SayHello', $result->routes);
        self::assertArrayHasKey('/pkg.Greeter/Chat', $result->routes);
    }

    public function testSkipsAnUnattributedMethod(): void
    {
        $result = new GrpcRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertCount(2, $result->routes);
        self::assertArrayNotHasKey('/pkg.Greeter/notAnRpc', $result->routes);
    }

    public function testSkipsAClassWithoutAServiceAttribute(): void
    {
        $result = new GrpcRouteAttributeReader()->readFile(self::$unattributedFixtureFile);

        self::assertSame([], $result->routes);
    }

    public function testEmitsTheAttributedMethodAsTheHandler(): void
    {
        $result = new GrpcRouteAttributeReader()->readFile(self::$fixtureFile);

        $printed = self::print($result->routes['/pkg.Greeter/SayHello']);

        self::assertStringContainsString('method: \'/pkg.Greeter/SayHello\'', $printed);
        self::assertStringContainsString('\\' . TestGrpcControllerFixture::class . '::class, \'sayHello\'', $printed);
    }

    public function testOmitsTheStreamingFlagsForAUnaryMethod(): void
    {
        $result = new GrpcRouteAttributeReader()->readFile(self::$fixtureFile);

        $printed = self::print($result->routes['/pkg.Greeter/SayHello']);

        self::assertStringNotContainsString('clientStreaming', $printed);
        self::assertStringNotContainsString('serverStreaming', $printed);
    }

    public function testEmitsTheStreamingFlagsForABidirectionalMethod(): void
    {
        $result = new GrpcRouteAttributeReader()->readFile(self::$fixtureFile);

        $printed = self::print($result->routes['/pkg.Greeter/Chat']);

        self::assertStringContainsString('clientStreaming: true', $printed);
        self::assertStringContainsString('serverStreaming: true', $printed);
    }

    public function testClassifiesAMiddlewareIntoEveryStageItImplements(): void
    {
        $result = new GrpcRouteAttributeReader()->readFile(self::$fixtureFile);

        $printed    = self::print($result->routes['/pkg.Greeter/Chat']);
        $middleware = '\\' . TestGrpcMiddlewareFixture::class;

        self::assertStringContainsString("routeMatchedMiddleware: [$middleware::class]", $printed);
        self::assertStringContainsString("routeDispatchedMiddleware: [$middleware::class]", $printed);
        self::assertStringContainsString("throwableCaughtMiddleware: [$middleware::class]", $printed);
        self::assertStringContainsString("sendingResponseMiddleware: [$middleware::class]", $printed);
        self::assertStringContainsString("responseSentMiddleware: [$middleware::class]", $printed);
    }

    public function testAMethodWithoutMiddlewareEmitsNone(): void
    {
        $result = new GrpcRouteAttributeReader()->readFile(self::$fixtureFile);

        $printed = self::print($result->routes['/pkg.Greeter/SayHello']);

        self::assertStringNotContainsString('Middleware:', $printed);
    }

    public function testAnUnparseableFileYieldsNoRoutes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sindri-grpc');

        self::assertNotFalse($file);

        file_put_contents($file, "<?php\n\n// no class here\n");

        $result = new GrpcRouteAttributeReader()->readFile($file);

        unlink($file);

        self::assertSame([], $result->routes);
    }

    public function testAMethodAttributeWithoutANameIsSkipped(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sindri-grpc');

        self::assertNotFalse($file);

        file_put_contents($file, <<<'PHP'
            <?php

            namespace Sindri\Tests\Tmp;

            use Valkyrja\Grpc\Routing\Attribute\Method;
            use Valkyrja\Grpc\Routing\Attribute\Service;

            #[Service(service: 'pkg.Empty')]
            final class TmpController
            {
                #[Method(name: '')]
                public static function nameless(): void
                {
                }
            }
            PHP);

        $result = new GrpcRouteAttributeReader()->readFile($file);

        unlink($file);

        self::assertSame([], $result->routes);
    }

    public function testAServiceAttributeWithoutANameIsIgnored(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sindri-grpc');

        self::assertNotFalse($file);

        file_put_contents($file, <<<'PHP'
            <?php

            namespace Sindri\Tests\Tmp;

            use Valkyrja\Grpc\Routing\Attribute\Method;
            use Valkyrja\Grpc\Routing\Attribute\Service;

            #[Service(service: '')]
            final class TmpController
            {
                #[Method(name: 'DoThing')]
                public static function doThing(): void
                {
                }
            }
            PHP);

        $result = new GrpcRouteAttributeReader()->readFile($file);

        unlink($file);

        self::assertSame([], $result->routes);
    }

    public function testAnUnresolvableMiddlewareIsSkipped(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sindri-grpc');

        self::assertNotFalse($file);

        file_put_contents($file, <<<'PHP'
            <?php

            namespace Sindri\Tests\Tmp;

            use Valkyrja\Grpc\Routing\Attribute\Method;
            use Valkyrja\Grpc\Routing\Attribute\Method\Middleware;
            use Valkyrja\Grpc\Routing\Attribute\Service;

            #[Service(service: 'pkg.Loose')]
            final class TmpController
            {
                #[Method(name: 'DoThing')]
                #[Middleware(name: 123)]
                public static function doThing(): void
                {
                }
            }
            PHP);

        $result = new GrpcRouteAttributeReader()->readFile($file);

        unlink($file);

        self::assertArrayHasKey('/pkg.Loose/DoThing', $result->routes);
        self::assertStringNotContainsString('Middleware:', self::print($result->routes['/pkg.Loose/DoThing']));
    }

    public function testEmitsTheRequestAndResponseTypes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sindri-grpc');

        self::assertNotFalse($file);

        file_put_contents($file, <<<'PHP'
            <?php

            namespace Sindri\Tests\Tmp;

            use Valkyrja\Grpc\Routing\Attribute\Method;
            use Valkyrja\Grpc\Routing\Attribute\Service;
            use Valkyrja\Grpc\Routing\Data\Route;

            #[Service(service: 'pkg.Typed')]
            final class TmpController
            {
                #[Method(name: 'DoThing', requestType: Route::class, responseType: Route::class)]
                public static function doThing(): void
                {
                }
            }
            PHP);

        $result = new GrpcRouteAttributeReader()->readFile($file);

        unlink($file);

        $printed = self::print($result->routes['/pkg.Typed/DoThing']);

        self::assertStringContainsString('requestType: \Valkyrja\Grpc\Routing\Data\Route::class', $printed);
        self::assertStringContainsString('responseType: \Valkyrja\Grpc\Routing\Data\Route::class', $printed);
    }

    public function testAnExplicitHandlerAttributeWins(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sindri-grpc');

        self::assertNotFalse($file);

        file_put_contents($file, <<<'PHP'
            <?php

            namespace Sindri\Tests\Tmp;

            use Valkyrja\Grpc\Routing\Attribute\Method;
            use Valkyrja\Grpc\Routing\Attribute\Method\MethodHandler;
            use Valkyrja\Grpc\Routing\Attribute\Service;

            #[Service(service: 'pkg.Overridden')]
            final class TmpController
            {
                public static function actualHandler(): void
                {
                }

                #[Method(name: 'DoThing')]
                #[MethodHandler([self::class, 'actualHandler'])]
                public static function doThing(): void
                {
                }
            }
            PHP);

        $result = new GrpcRouteAttributeReader()->readFile($file);

        unlink($file);

        self::assertStringContainsString(
            'actualHandler',
            self::print($result->routes['/pkg.Overridden/DoThing'])
        );
    }
}
