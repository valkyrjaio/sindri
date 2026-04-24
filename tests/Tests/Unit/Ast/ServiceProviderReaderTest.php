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
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Sindri\Ast\ServiceProviderReader;
use Sindri\Tests\Classes\Provider\Sub\TestOtherServiceClass;
use Sindri\Tests\Classes\Provider\Sub\TestOtherServiceProviderClass;
use Sindri\Tests\Classes\Provider\Sub\TestServiceClass;
use Sindri\Tests\Classes\Provider\Sub\TestServiceProviderClass;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ServiceProviderReaderTest extends TestCase
{
    private static string $fixtureFile;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Provider/Sub/TestServiceProviderClass.php');

        self::$fixtureFile = $path;
    }

    // -----------------------------------------------------------------------
    // Happy-path fixture tests
    // -----------------------------------------------------------------------

    public function testReadFileExtractsServiceClasses(): void
    {
        $result = new ServiceProviderReader()->readFile(self::$fixtureFile);

        self::assertSame(
            [TestServiceClass::class, TestOtherServiceClass::class],
            $result->serviceClasses,
        );
    }

    public function testReadFileExtractsSelfClassPublisher(): void
    {
        $result = new ServiceProviderReader()->readFile(self::$fixtureFile);

        self::assertSame(
            [TestServiceProviderClass::class, 'publishTestService'],
            $result->publishers[TestServiceClass::class],
        );
    }

    public function testReadFileExtractsExplicitClassPublisher(): void
    {
        $result = new ServiceProviderReader()->readFile(self::$fixtureFile);

        self::assertSame(
            [TestOtherServiceProviderClass::class, 'publishTestOtherService'],
            $result->publishers[TestOtherServiceClass::class],
        );
    }

    public function testReadFileReturnsEmptyResultForFileWithNoClass(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, "<?php\ndeclare(strict_types=1);\nnamespace Test;\n");

        $result = new ServiceProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->serviceClasses);
        self::assertSame([], $result->publishers);
    }

    public function testReadFileReturnsEmptyResultForClassWithNoPublishersMethod(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, "<?php\ndeclare(strict_types=1);\nnamespace Test;\nclass EmptyProvider {}\n");

        $result = new ServiceProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->serviceClasses);
        self::assertSame([], $result->publishers);
    }

    public function testReadFileReturnsEmptyResultForClassWithNoNamespace(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, "<?php\ndeclare(strict_types=1);\nclass NoNsProvider {}\n");

        $result = new ServiceProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->serviceClasses);
        self::assertSame([], $result->publishers);
    }

    // -----------------------------------------------------------------------
    // extractPublishersMap — skip branches (lines 91, 98, 104, 108, 114)
    // -----------------------------------------------------------------------

    public function testExtractPublishersMapReturnsEmptyWhenPublishersMethodHasNoReturnArray(): void
    {
        // publishers() exists but has no `return [...]` statement → findReturnedArray returns null → line 91
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class NoReturnPublishers {
                public static function publishers(): array
                {
                    // intentionally no return statement
                }
            }
            PHP);

        $result = new ServiceProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->publishers);
    }

    public function testExtractPublishersMapSkipsNullArrayItems(): void
    {
        // Construct a ClassMethod with a return Array_ containing a null item — line 98 (not ArrayItem → continue)
        $reader = new class extends ServiceProviderReader {
            /** @return array<string, array{0: string, 1: string}> */
            public function callExtractPublishersMap(ClassMethod|null $method, array $useMap, string $namespace, string $currentClass = ''): array
            {
                return $this->extractPublishersMap($method, $useMap, $namespace, $currentClass);
            }
        };

        $method        = new ClassMethod(new Identifier('publishers'));
        $array         = new Array_([null]);
        $method->stmts = [new Return_($array)];

        $result = $reader->callExtractPublishersMap($method, [], 'Test', '');

        self::assertSame([], $result);
    }

    public function testExtractPublishersMapSkipsEntryWithNonClassConstFetchKey(): void
    {
        // publishers() returns ['string-key' => [self::class, 'publish']] → key is not ClassConstFetch → line 104
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class StringKeyPublisher {
                public static function publishers(): array
                {
                    return ['string-key' => [self::class, 'publish']];
                }
            }
            PHP);

        $result = new ServiceProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->publishers);
    }

    public function testExtractPublishersMapSkipsEntryWithNonArrayValue(): void
    {
        // publishers() returns [SomeClass::class => 'not-an-array'] → value is String_, not Array_ → line 108
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class NonArrayValuePublisher {
                public static function publishers(): array
                {
                    return [self::class => 'not-an-array'];
                }
            }
            PHP);

        $result = new ServiceProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->publishers);
    }

    public function testExtractPublishersMapSkipsEntryWithInvalidHandlerArray(): void
    {
        // publishers() returns [SomeClass::class => [self::class]] → 1-element array, not [class, method] → line 114
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            class BadHandlerPublisher {
                public static function publishers(): array
                {
                    return [self::class => [self::class]];
                }
            }
            PHP);

        $result = new ServiceProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->publishers);
    }
}