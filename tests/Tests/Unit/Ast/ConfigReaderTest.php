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
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use Sindri\Ast\ConfigReader;
use Sindri\Tests\Classes\Config\Provider\TestComponentProviderClass;
use Sindri\Tests\Unit\Abstract\TestCase;

use function dirname;

final class ConfigReaderTest extends TestCase
{
    private static string $fixtureFile;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Config/TestConfigClass.php');

        self::$fixtureFile = $path;
    }

    // -----------------------------------------------------------------------
    // Happy-path fixture tests
    // -----------------------------------------------------------------------

    public function testReadFileExtractsNamespace(): void
    {
        $result = new ConfigReader()->readFile(self::$fixtureFile);

        self::assertSame('Sindri\\Tests\\Classes', $result->namespace);
    }

    public function testReadFileExtractsDirAsPsr4Root(): void
    {
        $fixtureDir     = dirname(self::$fixtureFile);
        $expectedSrcDir = dirname($fixtureDir);

        $result = new ConfigReader()->readFile(self::$fixtureFile);

        self::assertSame($expectedSrcDir, $result->dir);
    }

    public function testReadFileExtractsAbsoluteDataPath(): void
    {
        $fixtureDir       = dirname(self::$fixtureFile);
        $appRoot          = dirname($fixtureDir, 2);
        $expectedDataPath = $appRoot . '/Classes/Config/Data';

        $result = new ConfigReader()->readFile(self::$fixtureFile);

        self::assertSame($expectedDataPath, $result->dataPath);
    }

    public function testReadFileExtractsDataNamespace(): void
    {
        $result = new ConfigReader()->readFile(self::$fixtureFile);

        self::assertSame('Sindri\\Tests\\Classes\\Config\\Data', $result->dataNamespace);
    }

    public function testReadFileExtractsProviders(): void
    {
        $result = new ConfigReader()->readFile(self::$fixtureFile);

        self::assertSame([TestComponentProviderClass::class], $result->providers);
    }

    // -----------------------------------------------------------------------
    // readFile early-return branches (lines 56, 63, 69, 90)
    // -----------------------------------------------------------------------

    public function testReadFileReturnsEmptyResultForFileWithNoClass(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sindri_cfg') . '.php';
        file_put_contents($tmp, "<?php\ndeclare(strict_types=1);\nnamespace Test;\n");

        $result = new ConfigReader()->readFile($tmp);

        @unlink($tmp);

        self::assertSame('', $result->namespace);
    }

    public function testReadFileReturnsEmptyResultForClassWithNoConstructor(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sindri_cfg') . '.php';
        file_put_contents($tmp, "<?php\ndeclare(strict_types=1);\nnamespace Test;\nclass NoCtorConfig {}\n");

        $result = new ConfigReader()->readFile($tmp);

        @unlink($tmp);

        self::assertSame('', $result->namespace);
    }

    public function testReadFileReturnsEmptyResultForConstructorWithNoParentCall(): void
    {
        // Constructor exists but calls nothing — findParentConstructArgs returns null → line 69
        $tmp = tempnam(sys_get_temp_dir(), 'sindri_cfg') . '.php';
        file_put_contents($tmp, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class NoParentCallConfig {
                public function __construct() {}
            }
            PHP);

        $result = new ConfigReader()->readFile($tmp);

        @unlink($tmp);

        self::assertSame('', $result->namespace);
    }

    public function testReadFileReturnsEmptyResultWhenNamespaceArgIsMissing(): void
    {
        // parent::__construct is called but without the required named args — line 90
        $tmp = tempnam(sys_get_temp_dir(), 'sindri_cfg') . '.php';
        file_put_contents($tmp, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class MissingArgsConfig {
                public function __construct() {
                    parent::__construct();
                }
            }
            PHP);

        $result = new ConfigReader()->readFile($tmp);

        @unlink($tmp);

        self::assertSame('', $result->namespace);
    }

    // -----------------------------------------------------------------------
    // findParentConstructArgs — continue branches (lines 111, 117, 121, 125, 132)
    // -----------------------------------------------------------------------

    public function testFindParentConstructArgsSkipsNonExpressionStatements(): void
    {
        // Constructor with an if statement (not Expression) → line 111 continue, then returns null → line 132
        $tmp = tempnam(sys_get_temp_dir(), 'sindri_cfg') . '.php';
        file_put_contents($tmp, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class IfStmtConfig {
                public function __construct() {
                    if (true) {}
                }
            }
            PHP);

        $result = new ConfigReader()->readFile($tmp);

        @unlink($tmp);

        self::assertSame('', $result->namespace);
    }

    public function testFindParentConstructArgsSkipsNonStaticCallExpressions(): void
    {
        // Constructor with a method call (not StaticCall) → line 117 continue
        $tmp = tempnam(sys_get_temp_dir(), 'sindri_cfg') . '.php';
        file_put_contents($tmp, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class MethodCallConfig {
                public function __construct() {
                    $this->setup();
                }
                private function setup(): void {}
            }
            PHP);

        $result = new ConfigReader()->readFile($tmp);

        @unlink($tmp);

        self::assertSame('', $result->namespace);
    }

    public function testFindParentConstructArgsSkipsStaticCallOnNonParentClass(): void
    {
        // Constructor with a non-parent static call → line 121 continue
        $tmp = tempnam(sys_get_temp_dir(), 'sindri_cfg') . '.php';
        file_put_contents($tmp, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class StaticCallConfig {
                public function __construct() {
                    SomeClass::someMethod();
                }
            }
            PHP);

        $result = new ConfigReader()->readFile($tmp);

        @unlink($tmp);

        self::assertSame('', $result->namespace);
    }

    public function testFindParentConstructArgsSkipsParentCallToNonConstructMethod(): void
    {
        // parent::someMethod() (not parent::__construct) → line 125 continue
        $tmp = tempnam(sys_get_temp_dir(), 'sindri_cfg') . '.php';
        file_put_contents($tmp, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class ParentOtherMethodConfig {
                public function __construct() {
                    parent::setUp();
                }
            }
            PHP);

        $result = new ConfigReader()->readFile($tmp);

        @unlink($tmp);

        self::assertSame('', $result->namespace);
    }

    // -----------------------------------------------------------------------
    // findNamedArgValue — missing arg returns null (line 150)
    // -----------------------------------------------------------------------

    public function testFindNamedArgValueReturnsNullWhenArgNotFound(): void
    {
        $reader = new class extends ConfigReader {
            /** @param Arg[] $args */
            public function callFindNamedArgValue(array $args, string $name): mixed
            {
                return $this->findNamedArgValue($args, $name);
            }
        };

        $result = $reader->callFindNamedArgValue([], 'nonexistent');

        self::assertNull($result);
    }

    // -----------------------------------------------------------------------
    // extractStringNamedArg — non-String_ node returns '' (line 166)
    // -----------------------------------------------------------------------

    public function testExtractStringNamedArgReturnsEmptyForNonStringNode(): void
    {
        $reader = new class extends ConfigReader {
            /** @param Arg[] $args */
            public function callExtractStringNamedArg(array $args, string $name): string
            {
                return $this->extractStringNamedArg($args, $name);
            }
        };

        // Pass a Variable node (not String_) as the arg value
        $arg    = new Arg(new Variable('x'), name: new Identifier('namespace'));
        $result = $reader->callExtractStringNamedArg([$arg], 'namespace');

        self::assertSame('', $result);
    }

    // -----------------------------------------------------------------------
    // resolvePathExpr — null expr returns '' (line 178), unknown type returns '' (line 199)
    // -----------------------------------------------------------------------

    public function testResolvePathExprReturnsEmptyForNullExpr(): void
    {
        $reader = new class extends ConfigReader {
            public function callResolvePathExpr(mixed $expr, string $fileDir): string
            {
                return $this->resolvePathExpr($expr, $fileDir);
            }
        };

        self::assertSame('', $reader->callResolvePathExpr(null, '/tmp'));
    }

    public function testResolvePathExprReturnsEmptyForUnknownExprType(): void
    {
        $reader = new class extends ConfigReader {
            public function callResolvePathExpr(mixed $expr, string $fileDir): string
            {
                return $this->resolvePathExpr($expr, $fileDir);
            }
        };

        // Variable node is not a String_, Dir, or Concat — triggers the final return ''
        self::assertSame('', $reader->callResolvePathExpr(new Variable('x'), '/tmp'));
    }

    public function testResolvePathExprReturnsStringValueForStringNode(): void
    {
        $reader = new class extends ConfigReader {
            public function callResolvePathExpr(mixed $expr, string $fileDir): string
            {
                return $this->resolvePathExpr($expr, $fileDir);
            }
        };

        self::assertSame('/absolute/path', $reader->callResolvePathExpr(new String_('/absolute/path'), '/tmp'));
    }

    // -----------------------------------------------------------------------
    // extractClassListNamedArg — non-Array_ node returns [] (line 237)
    // -----------------------------------------------------------------------

    public function testExtractClassListNamedArgReturnsEmptyWhenArgIsNotArray(): void
    {
        $reader = new class extends ConfigReader {
            /** @param Arg[] $args */
            public function callExtractClassListNamedArg(array $args, string $name, array $useMap, string $namespace): array
            {
                return $this->extractClassListNamedArg($args, $name, $useMap, $namespace);
            }
        };

        // Provide a String_ value instead of an Array_ for the 'providers' arg
        $arg    = new Arg(new String_('not-an-array'), name: new Identifier('providers'));
        $result = $reader->callExtractClassListNamedArg([$arg], 'providers', [], '');

        self::assertSame([], $result);
    }
}
