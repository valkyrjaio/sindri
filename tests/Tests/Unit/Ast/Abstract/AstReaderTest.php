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

namespace Sindri\Tests\Unit\Ast\Abstract;

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use RuntimeException;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Data\HandlerData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class AstReaderTest extends TestCase
{
    private AstReader $reader;

    protected function setUp(): void
    {
        $this->reader = new class extends AstReader {
            public function callParseFileToStmts(string $filePath): array
            {
                return $this->parseFileToStmts($filePath);
            }

            public function callExtractClassListFromKeys(ClassMethod|null $method, array $useMap, string $namespace): array
            {
                return $this->extractClassListFromKeys($method, $useMap, $namespace);
            }

            public function callExtractClassListFromValues(ClassMethod|null $method, array $useMap, string $namespace): array
            {
                return $this->extractClassListFromValues($method, $useMap, $namespace);
            }

            public function callExtractClassListFromArrayExpr(Array_ $array, array $useMap, string $namespace, string $currentClass = ''): array
            {
                return $this->extractClassListFromArrayExpr($array, $useMap, $namespace, $currentClass);
            }

            public function callExtractExprValue(mixed $expr, array $useMap, string $namespace, string $currentClass = ''): mixed
            {
                return $this->extractExprValue($expr, $useMap, $namespace, $currentClass);
            }

            public function callExtractHandlerFromArray(Array_ $array, array $useMap, string $namespace, string $currentClass = ''): HandlerData|null
            {
                return $this->extractHandlerFromArray($array, $useMap, $namespace, $currentClass);
            }

            public function callResolveClassName(string $shortName, array $useMap, string $namespace): string
            {
                return $this->resolveClassName($shortName, $useMap, $namespace);
            }

            public function callNameToFqn(Name $name, array $useMap, string $namespace): string
            {
                return $this->nameToFqn($name, $useMap, $namespace);
            }

            public function callBuildEnumCaseExpr(string $fqnColonCase): Expr
            {
                return $this->buildEnumCaseExpr($fqnColonCase);
            }
        };
    }

    // -------------------------------------------------------------------------
    // extractClassListFromKeys
    // -------------------------------------------------------------------------

    public function testExtractClassListFromKeysReturnsEmptyForNullMethod(): void
    {
        $result = $this->reader->callExtractClassListFromKeys(null, [], '');

        self::assertSame([], $result);
    }

    public function testExtractClassListFromKeysReturnsEmptyForMethodWithNoReturnArray(): void
    {
        $method        = new ClassMethod(new Identifier('test'));
        $method->stmts = [];

        $result = $this->reader->callExtractClassListFromKeys($method, [], 'App');

        self::assertSame([], $result);
    }

    public function testExtractClassListFromKeysExtractsClassConstFetchKeys(): void
    {
        $method  = new ClassMethod(new Identifier('publishers'));
        $keyNode = new ClassConstFetch(new FullyQualified('App\\Service'), new Identifier('class'));
        $item    = new ArrayItem(new String_('value'), $keyNode);
        $array   = new Array_([$item]);
        $return  = new Return_($array);

        $method->stmts = [$return];

        $result = $this->reader->callExtractClassListFromKeys($method, [], 'App');

        self::assertSame(['App\\Service'], $result);
    }

    public function testExtractClassListFromKeysSkipsNullItems(): void
    {
        $method = new ClassMethod(new Identifier('publishers'));
        $array  = new Array_([null]);
        $return = new Return_($array);

        $method->stmts = [$return];

        $result = $this->reader->callExtractClassListFromKeys($method, [], 'App');

        self::assertSame([], $result);
    }

    public function testExtractClassListFromKeysSkipsNonClassConstFetchKeys(): void
    {
        $method = new ClassMethod(new Identifier('publishers'));
        $item   = new ArrayItem(new String_('value'), new String_('string-key'));
        $array  = new Array_([$item]);
        $return = new Return_($array);

        $method->stmts = [$return];

        $result = $this->reader->callExtractClassListFromKeys($method, [], 'App');

        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // extractClassListFromValues — null item guard
    // -------------------------------------------------------------------------

    public function testExtractClassListFromValuesSkipsNullItems(): void
    {
        $method = new ClassMethod(new Identifier('listClasses'));
        $array  = new Array_([null]);
        $return = new Return_($array);

        $method->stmts = [$return];

        $result = $this->reader->callExtractClassListFromValues($method, [], 'App');

        self::assertSame([], $result);
    }

    public function testExtractClassListFromValuesReturnsEmptyForNullMethod(): void
    {
        $result = $this->reader->callExtractClassListFromValues(null, [], '');

        self::assertSame([], $result);
    }

    public function testExtractClassListFromValuesReturnsEmptyForMethodWithNoReturnArray(): void
    {
        $method        = new ClassMethod(new Identifier('test'));
        $method->stmts = [];

        $result = $this->reader->callExtractClassListFromValues($method, [], 'App');

        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // extractExprValue — Int_, Float_, UnaryMinus, InterpolatedString
    // -------------------------------------------------------------------------

    public function testExtractExprValueReturnsIntForIntNode(): void
    {
        $result = $this->reader->callExtractExprValue(new Int_(42), [], '');

        self::assertSame(42, $result);
    }

    public function testExtractExprValueReturnsFloatForFloatNode(): void
    {
        $result = $this->reader->callExtractExprValue(new Float_(3.14), [], '');

        self::assertSame(3.14, $result);
    }

    public function testExtractExprValueReturnsNegatedIntForUnaryMinusInt(): void
    {
        $result = $this->reader->callExtractExprValue(new UnaryMinus(new Int_(5)), [], '');

        self::assertSame(-5, $result);
    }

    public function testExtractExprValueReturnsNegatedFloatForUnaryMinusFloat(): void
    {
        $result = $this->reader->callExtractExprValue(new UnaryMinus(new Float_(1.5)), [], '');

        self::assertSame(-1.5, $result);
    }

    public function testExtractExprValueReturnsNullForUnaryMinusString(): void
    {
        $result = $this->reader->callExtractExprValue(new UnaryMinus(new String_('abc')), [], '');

        self::assertNull($result);
    }

    public function testExtractExprValueReturnsNullForInterpolatedString(): void
    {
        $result = $this->reader->callExtractExprValue(new InterpolatedString([]), [], '');

        self::assertNull($result);
    }

    // -------------------------------------------------------------------------
    // resolveClassName — prefix-alias branch
    // -------------------------------------------------------------------------

    public function testResolveClassNameResolvesViaExactAliasMatch(): void
    {
        $result = $this->reader->callResolveClassName('MyAlias', ['MyAlias' => 'Full\\Ns\\MyClass'], 'App');

        self::assertSame('Full\\Ns\\MyClass', $result);
    }

    public function testResolveClassNameResolvesViaPrefixAliasMatch(): void
    {
        $useMap = ['Routing' => 'Valkyrja\\Http\\Routing'];

        $result = $this->reader->callResolveClassName('Routing\\Attribute\\Route', $useMap, 'App');

        self::assertSame('Valkyrja\\Http\\Routing\\Attribute\\Route', $result);
    }

    public function testResolveClassNamePrependsNamespaceWhenNoAliasMatch(): void
    {
        $result = $this->reader->callResolveClassName('SomeClass', [], 'App\\Ns');

        self::assertSame('App\\Ns\\SomeClass', $result);
    }

    public function testResolveClassNameReturnsShortNameWhenNoNamespaceAndNoAlias(): void
    {
        $result = $this->reader->callResolveClassName('SomeClass', [], '');

        self::assertSame('SomeClass', $result);
    }

    // -------------------------------------------------------------------------
    // extractHandlerFromArray — edge cases
    // -------------------------------------------------------------------------

    public function testExtractHandlerFromArrayReturnsNullForNonTwoElementArray(): void
    {
        $array = new Array_([new ArrayItem(new String_('only-one'))]);

        $result = $this->reader->callExtractHandlerFromArray($array, [], '');

        self::assertNull($result);
    }

    public function testExtractHandlerFromArrayReturnsNullWhenFirstItemIsNull(): void
    {
        $array = new Array_([null, new ArrayItem(new String_('method'))]);

        $result = $this->reader->callExtractHandlerFromArray($array, [], '');

        self::assertNull($result);
    }

    public function testExtractHandlerFromArrayReturnsNullWhenSecondItemIsNull(): void
    {
        $classItem = new ArrayItem(
            new ClassConstFetch(new FullyQualified('App\\Controller'), new Identifier('class'))
        );
        $array = new Array_([$classItem, null]);

        $result = $this->reader->callExtractHandlerFromArray($array, [], '');

        self::assertNull($result);
    }

    public function testExtractHandlerFromArrayReturnsNullWhenClassItemIsNotClassConstFetch(): void
    {
        $array = new Array_([
            new ArrayItem(new String_('not-a-class')),
            new ArrayItem(new String_('method')),
        ]);

        $result = $this->reader->callExtractHandlerFromArray($array, [], '');

        self::assertNull($result);
    }

    public function testExtractHandlerFromArrayReturnsNullWhenMethodIsNotString(): void
    {
        $classItem  = new ArrayItem(new ClassConstFetch(new FullyQualified('App\\Cls'), new Identifier('class')));
        $methodItem = new ArrayItem(new Int_(42));
        $array      = new Array_([$classItem, $methodItem]);

        $result = $this->reader->callExtractHandlerFromArray($array, [], '');

        self::assertNull($result);
    }

    public function testExtractHandlerFromArrayReturnsHandlerDataForValidInput(): void
    {
        $classItem  = new ArrayItem(new ClassConstFetch(new FullyQualified('App\\Controller'), new Identifier('class')));
        $methodItem = new ArrayItem(new String_('handle'));
        $array      = new Array_([$classItem, $methodItem]);

        $result = $this->reader->callExtractHandlerFromArray($array, [], '');

        self::assertInstanceOf(HandlerData::class, $result);
        self::assertSame('App\\Controller', $result->class);
        self::assertSame('handle', $result->method);
    }

    // -------------------------------------------------------------------------
    // parseFileToStmts — unreadable file throws RuntimeException
    // -------------------------------------------------------------------------

    public function testParseFileToStmtsThrowsForUnreadableFile(): void
    {
        $this->expectException(RuntimeException::class);

        @$this->reader->callParseFileToStmts('/nonexistent/path/to/file.php');
    }

    // -------------------------------------------------------------------------
    // extractExprValue — ConstFetch(false) and ConstFetch(null)
    // -------------------------------------------------------------------------

    public function testExtractExprValueReturnsFalseForConstFetchFalse(): void
    {
        $result = $this->reader->callExtractExprValue(new ConstFetch(new Name('false')), [], '');

        self::assertFalse($result);
    }

    public function testExtractExprValueReturnsNullForConstFetchNull(): void
    {
        $result = $this->reader->callExtractExprValue(new ConstFetch(new Name('null')), [], '');

        self::assertNull($result);
    }

    public function testExtractExprValueReturnsNullForUnknownExprType(): void
    {
        $result = $this->reader->callExtractExprValue(new Variable('foo'), [], '');

        self::assertNull($result);
    }

    // -------------------------------------------------------------------------
    // extractExprValue — self::class and static::class resolve to $currentClass
    // -------------------------------------------------------------------------

    public function testExtractExprValueResolvesSelfClassToCurrentClass(): void
    {
        $expr   = new ClassConstFetch(new Name('self'), new Identifier('class'));
        $result = $this->reader->callExtractExprValue($expr, [], '', 'App\\MyClass');

        self::assertSame('App\\MyClass', $result);
    }

    public function testExtractExprValueResolvesStaticClassToCurrentClass(): void
    {
        $expr   = new ClassConstFetch(new Name('static'), new Identifier('class'));
        $result = $this->reader->callExtractExprValue($expr, [], '', 'App\\MyClass');

        self::assertSame('App\\MyClass', $result);
    }

    // -------------------------------------------------------------------------
    // extractExprValue — self::CASE and static::CASE resolve to currentClass::CASE
    // -------------------------------------------------------------------------

    public function testExtractExprValueResolvesSelfEnumCaseToCurrentClassCase(): void
    {
        $expr   = new ClassConstFetch(new Name('self'), new Identifier('MY_CASE'));
        $result = $this->reader->callExtractExprValue($expr, [], '', 'App\\MyEnum');

        self::assertSame('App\\MyEnum::MY_CASE', $result);
    }

    public function testExtractExprValueResolvesStaticEnumCaseToCurrentClassCase(): void
    {
        $expr   = new ClassConstFetch(new Name('static'), new Identifier('MY_CASE'));
        $result = $this->reader->callExtractExprValue($expr, [], '', 'App\\MyEnum');

        self::assertSame('App\\MyEnum::MY_CASE', $result);
    }

    // -------------------------------------------------------------------------
    // nameToFqn — FullyQualified name returns as-is
    // -------------------------------------------------------------------------

    public function testNameToFqnReturnsFullyQualifiedNameDirectly(): void
    {
        $result = $this->reader->callNameToFqn(new FullyQualified('Ns\\ClassName'), [], '');

        self::assertSame('Ns\\ClassName', $result);
    }

    // -------------------------------------------------------------------------
    // extractClassListFromArrayExpr — null item is skipped
    // -------------------------------------------------------------------------

    public function testExtractClassListFromArrayExprSkipsNullItems(): void
    {
        $item  = new ArrayItem(new ClassConstFetch(new FullyQualified('App\\Service'), new Identifier('class')));
        $array = new Array_([null, $item]);

        $result = $this->reader->callExtractClassListFromArrayExpr($array, [], 'App');

        self::assertSame(['App\\Service'], $result);
    }

    // -------------------------------------------------------------------------
    // buildEnumCaseExpr — input without '::' falls back to String_
    // -------------------------------------------------------------------------

    public function testBuildEnumCaseExprReturnsStringFallbackWhenNoDoubleColon(): void
    {
        $result = $this->reader->callBuildEnumCaseExpr('NoColonHere');

        self::assertInstanceOf(String_::class, $result);
        self::assertSame('NoColonHere', $result->value);
    }
}
