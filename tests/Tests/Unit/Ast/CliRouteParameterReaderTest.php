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
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\CliRouteParameterReader;
use Sindri\Ast\Data\CliArgumentParameterData;
use Sindri\Ast\Data\CliOptionParameterData;
use Sindri\Ast\Data\CliRouteData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class CliRouteParameterReaderTest extends TestCase
{
    private CliRouteParameterReader $reader;

    /** @var object{callBuildArgumentData: callable, callBuildOptionData: callable, callBuildArgumentExpr: callable, callBuildOptionExpr: callable, callBuildArgumentListExpr: callable, callBuildOptionListExpr: callable} */
    private object $proxy;

    protected function setUp(): void
    {
        $this->reader = new CliRouteParameterReader();

        $this->proxy = new class extends CliRouteParameterReader {
            /** @param Arg[] $args */
            public function callBuildArgumentData(array $args, array $useMap, string $namespace, string $currentClass): CliArgumentParameterData|null
            {
                return $this->buildArgumentData($args, $useMap, $namespace, $currentClass);
            }

            /** @param Arg[] $args */
            public function callBuildOptionData(array $args, array $useMap, string $namespace, string $currentClass): CliOptionParameterData|null
            {
                return $this->buildOptionData($args, $useMap, $namespace, $currentClass);
            }

            public function callBuildArgumentExpr(CliArgumentParameterData $data): Expr
            {
                return $this->buildArgumentExpr($data);
            }

            public function callBuildOptionExpr(CliOptionParameterData $data): Expr
            {
                return $this->buildOptionExpr($data);
            }

            /** @param CliArgumentParameterData[] $arguments */
            public function callBuildArgumentListExpr(array $arguments): Array_
            {
                return $this->buildArgumentListExpr($arguments);
            }

            /** @param CliOptionParameterData[] $options */
            public function callBuildOptionListExpr(array $options): Array_
            {
                return $this->buildOptionListExpr($options);
            }
        };
    }

    // -------------------------------------------------------------------------
    // updateArguments
    // -------------------------------------------------------------------------

    public function testUpdateArgumentsReturnsEmptyForMethodWithNoAttributes(): void
    {
        $method         = new ClassMethod(new Identifier('test'));
        $method->stmts  = [];
        $method->params = [];

        $result = $this->reader->updateArguments($method, [], 'Test', 'Test\\TestClass');

        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // updateOptions
    // -------------------------------------------------------------------------

    public function testUpdateOptionsReturnsEmptyForMethodWithNoAttributes(): void
    {
        $method         = new ClassMethod(new Identifier('test'));
        $method->stmts  = [];
        $method->params = [];

        $result = $this->reader->updateOptions($method, [], 'Test', 'Test\\TestClass');

        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // buildParameterArgs
    // -------------------------------------------------------------------------

    public function testBuildParameterArgsReturnsEmptyWhenNoArgumentsOrOptions(): void
    {
        $data   = new CliRouteData(name: 'test', description: 'test');
        $result = $this->reader->buildParameterArgs($data);

        self::assertSame([], $result);
    }

    public function testBuildParameterArgsIncludesArgumentsArgWhenPresent(): void
    {
        $data = new CliRouteData(
            name: 'test',
            description: 'test',
            arguments: [new CliArgumentParameterData(name: 'file', description: 'Input file')],
        );

        $args = $this->reader->buildParameterArgs($data);

        self::assertCount(1, $args);
        self::assertInstanceOf(Arg::class, $args[0]);
        self::assertInstanceOf(Identifier::class, $args[0]->name);
        self::assertSame('arguments', $args[0]->name->toString());
    }

    public function testBuildParameterArgsIncludesOptionsArgWhenPresent(): void
    {
        $data = new CliRouteData(
            name: 'test',
            description: 'test',
            options: [new CliOptionParameterData(name: '--format', description: 'Output format')],
        );

        $args = $this->reader->buildParameterArgs($data);

        self::assertCount(1, $args);
        self::assertInstanceOf(Arg::class, $args[0]);
        self::assertInstanceOf(Identifier::class, $args[0]->name);
        self::assertSame('options', $args[0]->name->toString());
    }

    // -------------------------------------------------------------------------
    // buildArgumentData
    // -------------------------------------------------------------------------

    public function testBuildArgumentDataReturnsNullWhenNameIsEmpty(): void
    {
        $args = [
            new Arg(value: new String_(''), name: new Identifier('name')),
            new Arg(value: new String_('A description'), name: new Identifier('description')),
        ];

        $result = $this->proxy->callBuildArgumentData($args, [], 'Test', 'Test\\TestClass');

        self::assertNull($result);
    }

    public function testBuildArgumentDataReturnsNullWhenDescriptionIsEmpty(): void
    {
        $args = [
            new Arg(value: new String_('file'), name: new Identifier('name')),
            new Arg(value: new String_(''), name: new Identifier('description')),
        ];

        $result = $this->proxy->callBuildArgumentData($args, [], 'Test', 'Test\\TestClass');

        self::assertNull($result);
    }

    public function testBuildArgumentDataBuildsDataWithNameAndDescription(): void
    {
        $args = [
            new Arg(value: new String_('file'), name: new Identifier('name')),
            new Arg(value: new String_('Input file'), name: new Identifier('description')),
        ];

        $result = $this->proxy->callBuildArgumentData($args, [], 'Test', 'Test\\TestClass');

        self::assertInstanceOf(CliArgumentParameterData::class, $result);
        self::assertSame('file', $result->name);
        self::assertSame('Input file', $result->description);
        self::assertNull($result->cast);
    }

    // -------------------------------------------------------------------------
    // buildOptionData
    // -------------------------------------------------------------------------

    public function testBuildOptionDataReturnsNullWhenNameIsEmpty(): void
    {
        $args = [
            new Arg(value: new String_(''), name: new Identifier('name')),
            new Arg(value: new String_('A description'), name: new Identifier('description')),
        ];

        $result = $this->proxy->callBuildOptionData($args, [], 'Test', 'Test\\TestClass');

        self::assertNull($result);
    }

    public function testBuildOptionDataReturnsNullWhenDescriptionIsEmpty(): void
    {
        $args = [
            new Arg(value: new String_('--format'), name: new Identifier('name')),
            new Arg(value: new String_(''), name: new Identifier('description')),
        ];

        $result = $this->proxy->callBuildOptionData($args, [], 'Test', 'Test\\TestClass');

        self::assertNull($result);
    }

    public function testBuildOptionDataBuildsDataWithNameAndDescription(): void
    {
        $args = [
            new Arg(value: new String_('--format'), name: new Identifier('name')),
            new Arg(value: new String_('Output format'), name: new Identifier('description')),
        ];

        $result = $this->proxy->callBuildOptionData($args, [], 'Test', 'Test\\TestClass');

        self::assertInstanceOf(CliOptionParameterData::class, $result);
        self::assertSame('--format', $result->name);
        self::assertSame('Output format', $result->description);
        self::assertNull($result->cast);
    }

    // -------------------------------------------------------------------------
    // buildArgumentExpr
    // -------------------------------------------------------------------------

    public function testBuildArgumentExprProducesConstFetchNullWhenCastIsNull(): void
    {
        $data = new CliArgumentParameterData(name: 'file', description: 'Input file');
        $expr = $this->proxy->callBuildArgumentExpr($data);

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

    public function testBuildArgumentExprProducesClassConstFetchWhenCastIsNonNull(): void
    {
        $data = new CliArgumentParameterData(
            name: 'file',
            description: 'Input file',
            cast: 'Valkyrja\\Cli\\Routing\\Enum\\ArgumentMode::REQUIRED',
        );

        $expr = $this->proxy->callBuildArgumentExpr($data);

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
    // buildOptionExpr
    // -------------------------------------------------------------------------

    public function testBuildOptionExprProducesConstFetchNullWhenCastIsNull(): void
    {
        $data = new CliOptionParameterData(name: '--format', description: 'Output format');
        $expr = $this->proxy->callBuildOptionExpr($data);

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

    public function testBuildOptionExprIncludesShortNamesArgWhenPresent(): void
    {
        $data = new CliOptionParameterData(name: '--format', description: 'Output format', shortNames: ['-f']);
        $expr = $this->proxy->callBuildOptionExpr($data);

        self::assertInstanceOf(New_::class, $expr);

        $shortNamesArg = null;

        foreach ($expr->args as $arg) {
            if ($arg instanceof Arg && $arg->name instanceof Identifier && $arg->name->toString() === 'shortNames') {
                $shortNamesArg = $arg;
                break;
            }
        }

        self::assertNotNull($shortNamesArg);
        self::assertInstanceOf(Array_::class, $shortNamesArg->value);
    }

    public function testBuildOptionExprIncludesValidValuesArgWhenPresent(): void
    {
        $data = new CliOptionParameterData(name: '--format', description: 'Output format', validValues: ['json', 'xml']);
        $expr = $this->proxy->callBuildOptionExpr($data);

        self::assertInstanceOf(New_::class, $expr);

        $validValuesArg = null;

        foreach ($expr->args as $arg) {
            if ($arg instanceof Arg && $arg->name instanceof Identifier && $arg->name->toString() === 'validValues') {
                $validValuesArg = $arg;
                break;
            }
        }

        self::assertNotNull($validValuesArg);
        self::assertInstanceOf(Array_::class, $validValuesArg->value);
    }

    public function testBuildOptionExprOmitsShortNamesArgWhenEmpty(): void
    {
        $data = new CliOptionParameterData(name: '--format', description: 'Output format');
        $expr = $this->proxy->callBuildOptionExpr($data);

        self::assertInstanceOf(New_::class, $expr);

        foreach ($expr->args as $arg) {
            if ($arg instanceof Arg && $arg->name instanceof Identifier && $arg->name->toString() === 'shortNames') {
                self::fail('shortNames arg should not be present when empty');
            }
        }

        self::assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // buildArgumentListExpr / buildOptionListExpr
    // -------------------------------------------------------------------------

    public function testBuildArgumentListExprBuildsArrayWithOneItem(): void
    {
        $data   = new CliArgumentParameterData(name: 'file', description: 'Input file');
        $result = $this->proxy->callBuildArgumentListExpr([$data]);

        self::assertInstanceOf(Array_::class, $result);
        self::assertCount(1, $result->items);
    }

    public function testBuildOptionListExprBuildsArrayWithOneItem(): void
    {
        $data   = new CliOptionParameterData(name: '--format', description: 'Output format');
        $result = $this->proxy->callBuildOptionListExpr([$data]);

        self::assertInstanceOf(Array_::class, $result);
        self::assertCount(1, $result->items);
    }
}