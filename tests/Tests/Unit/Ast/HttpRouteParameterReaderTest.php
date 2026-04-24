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
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\VariadicPlaceholder;
use Sindri\Ast\Data\HttpParameterData;
use Sindri\Ast\HttpRouteParameterReader;
use Sindri\Tests\Unit\Abstract\TestCase;

final class HttpRouteParameterReaderTest extends TestCase
{
    private HttpRouteParameterReader $reader;

    /** @var object */
    private object $proxy;

    protected function setUp(): void
    {
        $this->reader = new HttpRouteParameterReader();

        $this->proxy = new class extends HttpRouteParameterReader {
            /** @param Arg[] $args */
            public function callCollectInlineParameters(array $args, array $useMap, string $namespace, string $currentClass): array
            {
                return $this->collectInlineParameters($args, $useMap, $namespace, $currentClass);
            }

            /** @param array<array-key, Arg|VariadicPlaceholder> $args */
            public function callBuildParameterData(array $args, array $useMap, string $namespace, string $currentClass): HttpParameterData|null
            {
                return $this->buildParameterData($args, $useMap, $namespace, $currentClass);
            }

            public function callBuildParameterFromExpr(Expr $expr, array $useMap, string $namespace, string $currentClass): HttpParameterData|null
            {
                return $this->buildParameterFromExpr($expr, $useMap, $namespace, $currentClass);
            }

            public function callBuildParameterExpr(HttpParameterData $data): Expr
            {
                return $this->buildParameterExpr($data);
            }
        };
    }

    // -------------------------------------------------------------------------
    // updateParameters
    // -------------------------------------------------------------------------

    public function testUpdateParametersReturnsEmptyForMethodWithNoParamsOrAttributes(): void
    {
        $method         = new ClassMethod(new Identifier('test'));
        $method->stmts  = [];
        $method->params = [];

        $result = $this->reader->updateParameters([], $method, [], 'Test', 'Test\\TestClass');

        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // collectInlineParameters
    // -------------------------------------------------------------------------

    public function testCollectInlineParametersReturnsEmptyWhenArgIsNotArray(): void
    {
        $args = [new Arg(value: new String_('not-an-array'), name: new Identifier('parameters'))];

        $result = $this->proxy->callCollectInlineParameters($args, [], 'Test', 'Test\\TestClass');

        self::assertSame([], $result);
    }

    public function testCollectInlineParametersSkipsNullItems(): void
    {
        $nullItemArray = new Array_([null]);
        $args          = [new Arg(value: $nullItemArray, name: new Identifier('parameters'))];

        $result = $this->proxy->callCollectInlineParameters($args, [], 'Test', 'Test\\TestClass');

        self::assertSame([], $result);
    }

    public function testCollectInlineParametersSkipsNonNewExprItems(): void
    {
        $nonNewExpr    = new String_('not-a-new-expr');
        $nullItemArray = new Array_([new ArrayItem($nonNewExpr)]);
        $args          = [new Arg(value: $nullItemArray, name: new Identifier('parameters'))];

        $result = $this->proxy->callCollectInlineParameters($args, [], 'Test', 'Test\\TestClass');

        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // buildParameterFromExpr
    // -------------------------------------------------------------------------

    public function testBuildParameterFromExprReturnsNullForNonNewExpr(): void
    {
        $result = $this->proxy->callBuildParameterFromExpr(new String_('not-new'), [], 'Test', 'Test\\TestClass');

        self::assertNull($result);
    }

    // -------------------------------------------------------------------------
    // buildParameterData
    // -------------------------------------------------------------------------

    public function testBuildParameterDataReturnsNullWhenNameIsEmpty(): void
    {
        $args = [
            new Arg(value: new String_(''), name: new Identifier('name')),
            new Arg(value: new String_('[0-9]+'), name: new Identifier('regex')),
        ];

        $result = $this->proxy->callBuildParameterData($args, [], 'Test', 'Test\\TestClass');

        self::assertNull($result);
    }

    public function testBuildParameterDataReturnsNullWhenRegexIsEmpty(): void
    {
        $args = [
            new Arg(value: new String_('id'), name: new Identifier('name')),
            new Arg(value: new String_(''), name: new Identifier('regex')),
        ];

        $result = $this->proxy->callBuildParameterData($args, [], 'Test', 'Test\\TestClass');

        self::assertNull($result);
    }

    public function testBuildParameterDataBuildsDataWithNameAndRegex(): void
    {
        $args = [
            new Arg(value: new String_('id'), name: new Identifier('name')),
            new Arg(value: new String_('[0-9]+'), name: new Identifier('regex')),
        ];

        $result = $this->proxy->callBuildParameterData($args, [], 'Test', 'Test\\TestClass');

        self::assertInstanceOf(HttpParameterData::class, $result);
        self::assertSame('id', $result->name);
        self::assertSame('[0-9]+', $result->regex);
        self::assertNull($result->cast);
        self::assertFalse($result->isOptional);
        self::assertTrue($result->shouldCapture);
    }

    public function testBuildParameterDataFiltersOutVariadicPlaceholders(): void
    {
        $args = [
            new VariadicPlaceholder(),
            new Arg(value: new String_('id'), name: new Identifier('name')),
            new Arg(value: new String_('[0-9]+'), name: new Identifier('regex')),
        ];

        $result = $this->proxy->callBuildParameterData($args, [], 'Test', 'Test\\TestClass');

        self::assertInstanceOf(HttpParameterData::class, $result);
        self::assertSame('id', $result->name);
    }

    // -------------------------------------------------------------------------
    // buildParameterExpr
    // -------------------------------------------------------------------------

    public function testBuildParameterExprUsesStringNodeForPlainRegex(): void
    {
        $data = new HttpParameterData(name: 'id', regex: '[0-9]+');
        $expr = $this->proxy->callBuildParameterExpr($data);

        self::assertInstanceOf(New_::class, $expr);

        $regexArg = null;

        foreach ($expr->args as $arg) {
            if ($arg instanceof Arg && $arg->name instanceof Identifier && $arg->name->toString() === 'regex') {
                $regexArg = $arg;

                break;
            }
        }

        self::assertNotNull($regexArg);
        self::assertInstanceOf(String_::class, $regexArg->value);
    }

    public function testBuildParameterExprUsesEnumCaseExprForRegexWithDoubleColon(): void
    {
        $data = new HttpParameterData(name: 'method', regex: 'Valkyrja\\Http\\Message\\Enum\\RequestMethod::GET');
        $expr = $this->proxy->callBuildParameterExpr($data);

        self::assertInstanceOf(New_::class, $expr);

        $regexArg = null;

        foreach ($expr->args as $arg) {
            if ($arg instanceof Arg && $arg->name instanceof Identifier && $arg->name->toString() === 'regex') {
                $regexArg = $arg;

                break;
            }
        }

        self::assertNotNull($regexArg);
        self::assertInstanceOf(ClassConstFetch::class, $regexArg->value);
    }

    public function testBuildParameterExprProducesConstFetchNullWhenCastIsNull(): void
    {
        $data = new HttpParameterData(name: 'id', regex: '[0-9]+');
        $expr = $this->proxy->callBuildParameterExpr($data);

        self::assertInstanceOf(New_::class, $expr);

        $castArg = null;

        foreach ($expr->args as $arg) {
            if ($arg instanceof Arg && $arg->name instanceof Identifier && $arg->name->toString() === 'cast') {
                $castArg = $arg;

                break;
            }
        }

        self::assertNotNull($castArg);
        self::assertInstanceOf(ConstFetch::class, $castArg->value);
        self::assertSame('null', $castArg->value->name->toString());
    }

    public function testBuildParameterExprProducesClassConstFetchWhenCastIsNonNull(): void
    {
        $data = new HttpParameterData(
            name: 'id',
            regex: '[0-9]+',
            cast: 'Valkyrja\\Some\\Enum\\CastEnum::INT',
        );

        $expr = $this->proxy->callBuildParameterExpr($data);

        self::assertInstanceOf(New_::class, $expr);

        $castArg = null;

        foreach ($expr->args as $arg) {
            if ($arg instanceof Arg && $arg->name instanceof Identifier && $arg->name->toString() === 'cast') {
                $castArg = $arg;

                break;
            }
        }

        self::assertNotNull($castArg);
        self::assertInstanceOf(ClassConstFetch::class, $castArg->value);
    }

    // -------------------------------------------------------------------------
    // buildParameterListExpr
    // -------------------------------------------------------------------------

    public function testBuildParameterListExprBuildsArrayWithOneItem(): void
    {
        $data   = new HttpParameterData(name: 'id', regex: '[0-9]+');
        $result = $this->reader->buildParameterListExpr([$data]);

        self::assertInstanceOf(Array_::class, $result);
        self::assertCount(1, $result->items);
    }

    public function testBuildParameterListExprBuildsEmptyArrayForEmptyInput(): void
    {
        $result = $this->reader->buildParameterListExpr([]);

        self::assertInstanceOf(Array_::class, $result);
        self::assertCount(0, $result->items);
    }
}
