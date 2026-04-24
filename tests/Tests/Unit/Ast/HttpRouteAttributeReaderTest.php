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

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\HttpRouteAttributeReader;
use Sindri\Tests\Classes\Http\Controller\TestHttpControllerClass;
use Sindri\Tests\Classes\Http\Middleware\TestHttpMiddlewareClass;
use Sindri\Tests\Unit\Abstract\TestCase;

final class HttpRouteAttributeReaderTest extends TestCase
{
    private static string $fixtureFile;
    private static string $richFixtureFile;
    private static string $badFixtureFile;
    private static string $noNsFixtureFile;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Http/Controller/TestHttpControllerClass.php');

        self::$fixtureFile = $path;

        /** @var non-empty-string $richPath */
        $richPath = realpath(__DIR__ . '/../../Classes/Http/Controller/TestRichHttpControllerClass.php');

        self::$richFixtureFile = $richPath;

        /** @var non-empty-string $badPath */
        $badPath = realpath(__DIR__ . '/../../Classes/Http/Controller/TestBadHttpControllerClass.php');

        self::$badFixtureFile = $badPath;

        /** @var non-empty-string $noNsPath */
        $noNsPath = realpath(__DIR__ . '/../../Classes/Http/Controller/TestNoNsHttpControllerClass.php');

        self::$noNsFixtureFile = $noNsPath;
    }

    // -----------------------------------------------------------------------
    // Basic fixture tests
    // -----------------------------------------------------------------------

    public function testReadFileExtractsStaticRouteByName(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertArrayHasKey('test.route', $result->routes);
    }

    public function testReadFileExtractsDynamicRouteByName(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertArrayHasKey('test.dynamic', $result->routes);
    }

    public function testReadFileExtractsRouteDataForStaticRoute(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertArrayHasKey('test.route', $result->routeData);
        self::assertSame('/test', $result->routeData['test.route']->path);
        self::assertSame(false, $result->routeData['test.route']->isDynamic);
    }

    public function testReadFileExtractsRouteDataForDynamicRoute(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertArrayHasKey('test.dynamic', $result->routeData);
        self::assertSame('/test/{id}', $result->routeData['test.dynamic']->path);
        self::assertSame(true, $result->routeData['test.dynamic']->isDynamic);
    }

    public function testReadFileExtractsDynamicRouteParameters(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$fixtureFile);

        self::assertCount(1, $result->routeData['test.dynamic']->parameters);
        self::assertSame('id', $result->routeData['test.dynamic']->parameters[0]->name);
        self::assertSame('[0-9]+', $result->routeData['test.dynamic']->parameters[0]->regex);
    }

    public function testReadFileReturnsEmptyResultForFileWithNoClass(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, "<?php\ndeclare(strict_types=1);\nnamespace Test;\n");

        $result = new HttpRouteAttributeReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->routes);
        self::assertSame([], $result->routeData);
    }

    // -----------------------------------------------------------------------
    // No-namespace fixture — covers the $namespace === '' branch (line 83)
    // -----------------------------------------------------------------------

    public function testReadFileWithNoNamespaceClassExtractsRoutes(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$noNsFixtureFile);

        self::assertArrayHasKey('no-ns.route', $result->routes);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — class-level path/name prefix
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsClassPathPrefix(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        // listUsers has path '/users', class prefix '/api' → '/api/users'
        // then method #[RoutePath('/list')] → '/api/users/list'
        self::assertArrayHasKey('api.users.list.paginated', $result->routeData);
        self::assertStringContainsString('/api', $result->routeData['api.users.list.paginated']->path);
    }

    public function testReadRichFileExtractsClassNamePrefix(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        // Name prefix 'api' + route name 'users.list' + method name 'paginated'
        self::assertArrayHasKey('api.users.list.paginated', $result->routes);
    }

    public function testReadRichFileAppliesMethodLevelPathSuffix(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        // path = '/api/users/list' (class + route + method suffix)
        self::assertSame('/api/users/list', $result->routeData['api.users.list.paginated']->path);
    }

    public function testReadRichFileAppliesMethodLevelNameSuffix(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        // name = 'api.users.list.paginated' (class prefix + route name + method suffix)
        self::assertArrayHasKey('api.users.list.paginated', $result->routeData);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — middleware
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsMiddleware(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.users.create'];

        self::assertContains(TestHttpMiddlewareClass::class, $data->routeMatchedMiddleware);
        self::assertContains(TestHttpMiddlewareClass::class, $data->routeDispatchedMiddleware);
        self::assertContains(TestHttpMiddlewareClass::class, $data->throwableCaughtMiddleware);
        self::assertContains(TestHttpMiddlewareClass::class, $data->sendingResponseMiddleware);
        self::assertContains(TestHttpMiddlewareClass::class, $data->terminatedMiddleware);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — request/response struct
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsRequestStruct(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.users.update'];

        self::assertSame(TestHttpMiddlewareClass::class, $data->requestStruct);
    }

    public function testReadRichFileExtractsResponseStruct(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.users.update'];

        self::assertSame(TestHttpMiddlewareClass::class, $data->responseStruct);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — inline request methods via RouteRequestMethod attr
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsRequestMethodAttribute(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.orders.post'];

        self::assertNotEmpty($data->requestMethods);
        self::assertContains('Valkyrja\\Http\\Message\\Enum\\RequestMethod::POST', $data->requestMethods);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — inline DynamicRoute parameters
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsInlineDynamicRouteParameters(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.items.show'];

        self::assertCount(1, $data->parameters);
        self::assertSame('id', $data->parameters[0]->name);
        self::assertSame('[0-9]+', $data->parameters[0]->regex);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — PHP method-parameter-level #[Parameter]
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsPhpParamLevelParameter(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.products.show'];

        self::assertCount(1, $data->parameters);
        self::assertSame('slug', $data->parameters[0]->name);
        self::assertSame('[a-z-]+', $data->parameters[0]->regex);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — inline middleware arrays in #[Route] (lines 204-220)
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsInlineRouteMatchedMiddleware(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.inline.middleware'];

        self::assertContains(TestHttpMiddlewareClass::class, $data->routeMatchedMiddleware);
    }

    public function testReadRichFileExtractsInlineRouteDispatchedMiddleware(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.inline.middleware'];

        self::assertContains(TestHttpMiddlewareClass::class, $data->routeDispatchedMiddleware);
    }

    public function testReadRichFileExtractsInlineThrowableCaughtMiddleware(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.inline.middleware'];

        self::assertContains(TestHttpMiddlewareClass::class, $data->throwableCaughtMiddleware);
    }

    public function testReadRichFileExtractsInlineSendingResponseMiddleware(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.inline.middleware'];

        self::assertContains(TestHttpMiddlewareClass::class, $data->sendingResponseMiddleware);
    }

    public function testReadRichFileExtractsInlineTerminatedMiddleware(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.inline.middleware'];

        self::assertContains(TestHttpMiddlewareClass::class, $data->terminatedMiddleware);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — RouteHandler attribute (lines 330-333)
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsCustomHandlerFromRouteHandlerAttr(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.custom.handler'];

        self::assertSame(TestHttpControllerClass::class, $data->handler->class);
        self::assertSame('staticAction', $data->handler->method);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — inline requestMethods in #[Route] (line 357)
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsInlineRequestMethodsFromRouteAttr(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.inline.methods'];

        self::assertContains('Valkyrja\\Http\\Message\\Enum\\RequestMethod::POST', $data->requestMethods);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — enum-case regex in Parameter (line 693)
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsParameterWithEnumCaseRegex(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.enum.method'];

        self::assertCount(1, $data->parameters);
        self::assertStringContainsString('::', $data->parameters[0]->regex);
    }

    // -----------------------------------------------------------------------
    // Rich fixture tests — Parameter with non-null cast (line 702)
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsParameterWithCast(): void
    {
        $result = new HttpRouteAttributeReader()->readFile(self::$richFixtureFile);

        $data = $result->routeData['api.cast.param'];

        self::assertCount(1, $data->parameters);
        self::assertNotNull($data->parameters[0]->cast);
    }

    // -----------------------------------------------------------------------
    // Bad fixture tests — skip/continue branches
    // -----------------------------------------------------------------------

    public function testReadBadFileSkipsNonStringMiddlewareWithContinue(): void
    {
        // #[Middleware(true)] resolves to bool true (not a string) → triggers continue branch
        $result = new HttpRouteAttributeReader()->readFile(self::$badFixtureFile);

        // The route itself is valid; only the middleware arg is bad and gets skipped
        self::assertArrayHasKey('bad.non-string-middleware', $result->routes);
    }

    public function testReadBadFileSkipsRouteWithEmptyName(): void
    {
        // #[Route(name: '')] causes buildRouteData to return null (line 179)
        $result = new HttpRouteAttributeReader()->readFile(self::$badFixtureFile);

        self::assertArrayNotHasKey('', $result->routes);
    }

    public function testReadBadFileSkipsNonNewExpressionInInlineParameters(): void
    {
        // parameters: ['not-a-parameter'] — a String_ node (not New_) → buildParameterFromExpr returns null (line 574)
        $result = new HttpRouteAttributeReader()->readFile(self::$badFixtureFile);

        self::assertArrayHasKey('bad.non-new', $result->routes);
        self::assertSame([], $result->routeData['bad.non-new']->parameters);
    }

    public function testReadBadFileSkipsParameterWithEmptyName(): void
    {
        // #[Parameter(name: '')] → buildParameterData returns null (line 598)
        $result = new HttpRouteAttributeReader()->readFile(self::$badFixtureFile);

        self::assertArrayHasKey('bad.empty-param', $result->routes);
        self::assertSame([], $result->routeData['bad.empty-param']->parameters);
    }

    // -----------------------------------------------------------------------
    // Direct AST test — null item in inline parameters array (line 528)
    // -----------------------------------------------------------------------

    public function testUpdateParametersSkipsNullItemsInInlineArray(): void
    {
        $reader = new class extends HttpRouteAttributeReader {
            /** @return array<mixed> */
            public function callUpdateParameters(array $args, ClassMethod $method, array $useMap, string $namespace, string $currentClass): array
            {
                return $this->updateParameters($args, $method, $useMap, $namespace, $currentClass);
            }
        };

        $method         = new ClassMethod(new Identifier('testAction'));
        $method->stmts  = [];
        $method->params = [];

        // Inline parameters array containing a null item (as PHP-Parser may produce internally)
        $nullItemArray = new Array_([null]);
        $args          = [new Arg(value: $nullItemArray, name: new Identifier('parameters'))];

        $result = $reader->callUpdateParameters($args, $method, [], 'Test', 'Test\\TestClass');

        self::assertSame([], $result);
    }
}