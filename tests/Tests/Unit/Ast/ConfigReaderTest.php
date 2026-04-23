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

    public function testReadFileExtractsNamespace(): void
    {
        $result = new ConfigReader()->readFile(self::$fixtureFile);

        self::assertSame('Sindri\\Tests\\Classes', $result->namespace);
    }

    public function testReadFileExtractsDirAsPsr4Root(): void
    {
        // The fixture has:
        //   - file namespace: Sindri\Tests\Classes\Config (4 segs)
        //   - base namespace:  Sindri\Tests\Classes        (3 segs)
        //   - depth diff = 1, so PSR-4 root = dirname(fixtureDir)
        //
        // The raw dir in the config is __DIR__ . '/../..' = Tests/ (2 levels up),
        // which is the "app root", NOT the PSR-4 root. ConfigReader must derive
        // the PSR-4 root from the file's position in the namespace hierarchy.
        $fixtureDir     = dirname(self::$fixtureFile);  // .../Classes/Config
        $expectedSrcDir = dirname($fixtureDir);           // .../Classes

        $result = new ConfigReader()->readFile(self::$fixtureFile);

        self::assertSame($expectedSrcDir, $result->dir);
    }

    public function testReadFileExtractsAbsoluteDataPath(): void
    {
        // dataPath in the fixture is 'Classes/Config/Data' (relative to the app root).
        // App root = __DIR__ . '/../..' = .../Tests
        // Absolute dataPath = .../Tests/Classes/Config/Data
        $fixtureDir       = dirname(self::$fixtureFile);  // .../Classes/Config
        $appRoot          = dirname($fixtureDir, 2); // .../Tests (2 levels up)
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
}
