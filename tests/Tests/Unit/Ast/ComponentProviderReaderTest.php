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

use Sindri\Ast\ComponentProviderReader;
use Sindri\Tests\Classes\Provider\Sub\TestServiceProviderClass;
use Sindri\Tests\Classes\Provider\Sub\TestSubComponentProviderClass;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ComponentProviderReaderTest extends TestCase
{
    private static string $fixtureFile;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Provider/TestComponentProviderClass.php');

        self::$fixtureFile = $path;
    }

    public function testReadFileExtractsComponentProviders(): void
    {
        $result = new ComponentProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([TestSubComponentProviderClass::class], $result->componentProviders);
    }

    public function testReadFileExtractsServiceProviders(): void
    {
        $result = new ComponentProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([TestServiceProviderClass::class], $result->serviceProviders);
    }

    public function testReadFileExtractsEmptyListenerProviders(): void
    {
        $result = new ComponentProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([], $result->listenerProviders);
    }

    public function testReadFileExtractsEmptyCliRouteProviders(): void
    {
        $result = new ComponentProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([], $result->cliRouteProviders);
    }

    public function testReadFileExtractsEmptyHttpRouteProviders(): void
    {
        $result = new ComponentProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([], $result->httpRouteProviders);
    }

    public function testReadFileReturnsEmptyResultForFileWithNoClass(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, "<?php\ndeclare(strict_types=1);\nnamespace Test;\n");

        $result = new ComponentProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->componentProviders);
        self::assertSame([], $result->serviceProviders);
        self::assertSame([], $result->listenerProviders);
        self::assertSame([], $result->cliRouteProviders);
        self::assertSame([], $result->httpRouteProviders);
    }
}
