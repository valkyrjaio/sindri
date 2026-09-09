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
use Sindri\Ast\Data\Result\QueueRouteAttributeResult;
use Sindri\Ast\QueueRouteAttributeReader;
use Sindri\Tests\Unit\Abstract\TestCase;

use function file_put_contents;
use function realpath;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class QueueRouteAttributeReaderTest extends TestCase
{
    private static string $fixtureFile;
    private static string $richFixtureFile;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Fixtures/Queue/Controller/TestQueueControllerFixture.php');

        self::$fixtureFile = $path;

        /** @var non-empty-string $richPath */
        $richPath = realpath(__DIR__ . '/../../Fixtures/Queue/Controller/TestRichQueueControllerFixture.php');

        self::$richFixtureFile = $richPath;
    }

    public function testReadFileExtractsRouteByJobName(): void
    {
        $result = new QueueRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertArrayHasKey('SendWelcomeEmail', $result->routes);
        self::assertCount(1, $result->routes);
    }

    public function testTheGeneratedExpressionBuildsTheFrameworkRoute(): void
    {
        $result = new QueueRouteAttributeReader()->readFile(self::$fixtureFile);
        $php    = new Standard()->prettyPrintExpr($result->routes['SendWelcomeEmail']);

        self::assertStringContainsString('new \Valkyrja\Queue\Routing\Data\Route(', $php);
        self::assertStringContainsString("name: 'SendWelcomeEmail'", $php);
        self::assertStringContainsString("description: 'Send the welcome email'", $php);
    }

    public function testAMethodNameAttributeOverridesTheJobName(): void
    {
        $result = new QueueRouteAttributeReader()->readFile(self::$richFixtureFile);

        self::assertArrayHasKey('ChargeCard.v2', $result->routes);
        self::assertArrayNotHasKey('ChargeCard', $result->routes);
    }

    public function testARouteHandlerAttributeIsUsedAsTheHandler(): void
    {
        $result = new QueueRouteAttributeReader()->readFile(self::$richFixtureFile);
        $php    = new Standard()->prettyPrintExpr($result->routes['ChargeCard.v2']);

        self::assertStringContainsString('handler:', $php);
        self::assertStringContainsString('TestRichQueueControllerFixture', $php);
    }

    public function testMiddlewareIsClassifiedIntoEveryStageItImplements(): void
    {
        $result = new QueueRouteAttributeReader()->readFile(self::$richFixtureFile);
        $php    = new Standard()->prettyPrintExpr($result->routes['ChargeCard.v2']);

        // One attribute, five lists — the reader mirrors the runtime collector
        self::assertStringContainsString('routeMatchedMiddleware:', $php);
        self::assertStringContainsString('routeDispatchedMiddleware:', $php);
        self::assertStringContainsString('throwableCaughtMiddleware:', $php);
        self::assertStringContainsString('settlingResultMiddleware:', $php);
        self::assertStringContainsString('resultSettledMiddleware:', $php);
    }

    public function testARouteWithNoMiddlewareOmitsEveryMiddlewareArgument(): void
    {
        $result = new QueueRouteAttributeReader()->readFile(self::$fixtureFile);
        $php    = new Standard()->prettyPrintExpr($result->routes['SendWelcomeEmail']);

        self::assertStringNotContainsString('Middleware:', $php);
    }

    public function testARouteMissingItsNameIsSkipped(): void
    {
        $result = new QueueRouteAttributeReader()->readFile(self::$richFixtureFile);

        self::assertCount(1, $result->routes);
    }

    public function testAnUnparsableFileYieldsNoRoutes(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'queue-reader');

        file_put_contents($file, "<?php\n\n// no class here\n");

        $result = new QueueRouteAttributeReader()->readFile($file);

        unlink($file);

        self::assertInstanceOf(QueueRouteAttributeResult::class, $result);
        self::assertSame([], $result->routes);
    }
}
