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

use Sindri\Ast\ListenerProviderReader;
use Sindri\Tests\Classes\Event\TestListenerClass;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ListenerProviderReaderTest extends TestCase
{
    private static string $fixtureFile;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Event/Provider/TestListenerProviderClass.php');

        self::$fixtureFile = $path;
    }

    public function testReadFileExtractsListenerClasses(): void
    {
        $result = new ListenerProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([TestListenerClass::class], $result->listenerClasses);
    }

    public function testReadFileExtractsEmptyListeners(): void
    {
        $result = new ListenerProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([], $result->listeners);
    }

    public function testReadFileReturnsEmptyResultForFileWithNoClass(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, "<?php\ndeclare(strict_types=1);\nnamespace Test;\n");

        $result = new ListenerProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->listenerClasses);
        self::assertSame([], $result->listeners);
    }
}