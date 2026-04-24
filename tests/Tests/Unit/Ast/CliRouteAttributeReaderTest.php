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

namespace Sindri\Tests\Unit\Ast;

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use Sindri\Ast\CliRouteAttributeReader;
use Sindri\Ast\Data\CliRouteData;
use Sindri\Ast\Data\HandlerData;
use Sindri\Tests\Classes\Cli\Controller\TestCliControllerClass;
use Sindri\Tests\Unit\Abstract\TestCase;

final class CliRouteAttributeReaderTest extends TestCase
{
    private static string $fixtureFile;
    private static string $richFixtureFile;
    private static string $badFixtureFile;
    private static string $noNsFixtureFile;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Cli/Controller/TestCliControllerClass.php');

        self::$fixtureFile = $path;

        /** @var non-empty-string $richPath */
        $richPath = realpath(__DIR__ . '/../../Classes/Cli/Controller/TestRichCliControllerClass.php');

        self::$richFixtureFile = $richPath;

        /** @var non-empty-string $badPath */
        $badPath = realpath(__DIR__ . '/../../Classes/Cli/Controller/TestBadCliControllerClass.php');

        self::$badFixtureFile = $badPath;

        /** @var non-empty-string $noNsPath */
        $noNsPath = realpath(__DIR__ . '/../../Classes/Cli/Controller/TestNoNsCliControllerClass.php');

        self::$noNsFixtureFile = $noNsPath;
    }

    // -----------------------------------------------------------------------
    // Basic fixture tests
    // -----------------------------------------------------------------------

    public function testReadFileExtractsRouteByName(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertArrayHasKey('test:cli-route', $result->routes);
    }

    public function testReadFileExtractsExpectedNumberOfRoutes(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertCount(1, $result->routes);
    }

    public function testReadFileReturnsEmptyResultForFileWithNoClass(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, "<?php\ndeclare(strict_types=1);\nnamespace Test;\n");

        $result = new CliRouteAttributeReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->routes);
    }

    // -----------------------------------------------------------------------
    // No-namespace fixture tests
    // -----------------------------------------------------------------------

    public function testReadNoNsFileExtractsRouteFromClassWithoutNamespace(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$noNsFixtureFile);

        self::assertArrayHasKey('no-ns:cli-route', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — middleware
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsMiddleware(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routes['rich:middleware'];

        self::assertNotNull($data);
    }

    public function testReadRichFileClassifiesRouteMatchedMiddleware(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$richFixtureFile);

        // TestCliMiddlewareClass implements RouteMatchedMiddlewareContract
        self::assertArrayHasKey('rich:middleware', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — inline middleware arrays in #[Route]
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsInlineMiddlewareArrays(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$richFixtureFile);

        self::assertArrayHasKey('rich:inline-middleware', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — custom RouteHandler
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsCustomRouteHandler(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$richFixtureFile);

        self::assertArrayHasKey('rich:custom-handler', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Bad fixture tests — skip/continue branches
    // -----------------------------------------------------------------------

    public function testReadBadFileSkipsNonStringMiddlewareWithContinue(): void
    {
        // #[Middleware(true)] resolves to bool true (not a string) → triggers continue branch
        $result = new CliRouteAttributeReader()->readFile(self::$badFixtureFile);

        // The route itself is valid; only the middleware arg is bad and gets skipped
        self::assertArrayHasKey('bad:non-string-middleware', $result->routes);
    }

    public function testReadBadFileSkipsRouteWithEmptyName(): void
    {
        // #[Route(name: '')] triggers return null in buildRouteData
        $result = new CliRouteAttributeReader()->readFile(self::$badFixtureFile);

        // Empty-name route is never added
        self::assertArrayNotHasKey('', $result->routes);
    }

    public function testReadBadFileSkipsArgumentWithNonStringDescription(): void
    {
        // #[ArgumentParameter(description: true)] — non-string description → triggers return null in buildArgumentData
        $result = new CliRouteAttributeReader()->readFile(self::$badFixtureFile);

        // The route exists; the bad argument is simply dropped
        self::assertArrayHasKey('bad:arg-non-string-desc', $result->routes);
    }

    public function testReadBadFileSkipsOptionWithNonStringDescription(): void
    {
        // #[OptionParameter(description: true)] — non-string description → triggers return null in buildOptionData
        $result = new CliRouteAttributeReader()->readFile(self::$badFixtureFile);

        // The route exists; the bad option is simply dropped
        self::assertArrayHasKey('bad:opt-non-string-desc', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — name override
    // -----------------------------------------------------------------------

    public function testReadRichFileAppliesNameOverride(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$richFixtureFile);

        // The #[RouteName('overridden:name')] replaces 'original:name'
        self::assertArrayHasKey('overridden:name', $result->routes);
        self::assertArrayNotHasKey('original:name', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — argument with cast
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsArgumentWithCast(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$richFixtureFile);

        self::assertArrayHasKey('rich:cast-arg', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — option with validValues
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsOptionWithValidValues(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$richFixtureFile);

        self::assertArrayHasKey('rich:valid-values', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — option with cast
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsOptionWithCast(): void
    {
        $result = new CliRouteAttributeReader()->readFile(self::$richFixtureFile);

        self::assertArrayHasKey('rich:cast-opt', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Direct tests via anonymous subclass — dead-code branches
    // -----------------------------------------------------------------------

    public function testBuildRouteExprWithNonNullHelpText(): void
    {
        $reader = new class extends CliRouteAttributeReader {
            public function callBuildRouteExpr(CliRouteData $data): Expr
            {
                return $this->buildRouteExpr($data);
            }
        };

        $data = new CliRouteData(
            name: 'test',
            description: 'test',
            helpText: new HandlerData(class: TestCliControllerClass::class, method: 'testAction'),
        );

        $expr = $reader->callBuildRouteExpr($data);

        self::assertNotNull($expr);
    }

    public function testExtractStringListFromArrayExprSkipsNullItems(): void
    {
        $reader = new class extends CliRouteAttributeReader {
            /** @return string[] */
            public function callExtractStringListFromArrayExpr(Array_ $array): array
            {
                return $this->extractStringListFromArrayExpr($array, [], '', '');
            }
        };

        // Construct an Array_ with a null item to trigger the `continue` branch
        $array        = new Array_([null, new ArrayItem(new String_('valid'))]);
        $result       = $reader->callExtractStringListFromArrayExpr($array);

        self::assertSame(['valid'], $result);
    }
}
