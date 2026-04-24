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

use PhpParser\Node\Expr;
use Sindri\Ast\Data\HandlerData;
use Sindri\Ast\Data\ListenerData;
use Sindri\Ast\ListenerAttributeReader;
use Sindri\Tests\Classes\Event\TestListenerClass;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ListenerAttributeReaderTest extends TestCase
{
    private static string $fixtureFile;
    private static string $richFixtureFile;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Event/TestListenerClass.php');

        self::$fixtureFile = $path;

        /** @var non-empty-string $richPath */
        $richPath = realpath(__DIR__ . '/../../Classes/Event/TestRichListenerClass.php');

        self::$richFixtureFile = $richPath;
    }

    public function testReadFileExtractsClassLevelListener(): void
    {
        $result = new ListenerAttributeReader()->readFile(self::$fixtureFile);

        self::assertArrayHasKey('test-class-listener', $result->listeners);
    }

    public function testReadFileExtractsMethodLevelListener(): void
    {
        $result = new ListenerAttributeReader()->readFile(self::$fixtureFile);

        self::assertArrayHasKey('test-method-listener', $result->listeners);
    }

    public function testReadFileExtractsBothListeners(): void
    {
        $result = new ListenerAttributeReader()->readFile(self::$fixtureFile);

        self::assertCount(2, $result->listeners);
    }

    public function testReadFileReturnsEmptyResultForFileWithNoClass(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, "<?php\ndeclare(strict_types=1);\nnamespace Test;\n");

        $result = new ListenerAttributeReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->listeners);
    }

    public function testReadFileExtractsListenersFromClassWithNoNamespace(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Event/TestListenerNoNsClass.php');

        $result = new ListenerAttributeReader()->readFile($path);

        self::assertArrayHasKey('no-ns-class-listener', $result->listeners);
        self::assertArrayHasKey('no-ns-method-listener', $result->listeners);
    }

    public function testReadFileSkipsListenerWithEmptyEventId(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';
        file_put_contents($tmpFile, <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Test;
            use Valkyrja\Event\Attribute\Listener;
            #[Listener(eventId: '', name: 'bad-listener')]
            class BadListener {}
            PHP);

        $result = new ListenerAttributeReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([], $result->listeners);
    }

    // -----------------------------------------------------------------------
    // Rich fixture — ListenerHandler attribute resolves handler
    // -----------------------------------------------------------------------

    public function testReadRichFileExtractsListenerWithHandlerFromListenerHandlerAttr(): void
    {
        $result = new ListenerAttributeReader()->readFile(self::$richFixtureFile);

        self::assertArrayHasKey('handler-class-listener', $result->listeners);
    }

    public function testReadRichFileExtractsMethodListenerWithInlineHandler(): void
    {
        $result = new ListenerAttributeReader()->readFile(self::$richFixtureFile);

        self::assertArrayHasKey('inline-handler-method-listener', $result->listeners);
    }

    public function testReadRichFileResolvesInlineHandlerFromListenerAttrDirectly(): void
    {
        /** @var array<string, ListenerData> $captured */
        $captured = [];

        $reader = new class($captured) extends ListenerAttributeReader {
            /** @param array<string, ListenerData> $captured */
            public function __construct(private array &$captured)
            {
            }

            public function buildListenerExpr(ListenerData $data): Expr
            {
                $this->captured[$data->name] = $data;

                return parent::buildListenerExpr($data);
            }
        };

        $reader->readFile(self::$richFixtureFile);

        // The inline handler: handler provided directly in #[Listener(handler: [...])]
        // hits the `$handlerRaw instanceof HandlerData ? $handlerRaw : ...` true branch
        self::assertArrayHasKey('inline-handler-method-listener', $captured);
        self::assertSame(TestListenerClass::class, $captured['inline-handler-method-listener']->handler?->class ?? null);
        self::assertSame('handle', $captured['inline-handler-method-listener']->handler?->method ?? null);
    }

    public function testReadRichFileResolvesHandlerClassFromListenerHandlerAttr(): void
    {
        /** @var array<string, ListenerData> $captured */
        $captured = [];

        $reader = new class($captured) extends ListenerAttributeReader {
            /** @param array<string, ListenerData> $captured */
            public function __construct(private array &$captured)
            {
            }

            public function buildListenerExpr(ListenerData $data): Expr
            {
                $this->captured[$data->name] = $data;

                return parent::buildListenerExpr($data);
            }
        };

        $reader->readFile(self::$richFixtureFile);

        // The #[ListenerHandler([TestListenerClass::class, 'handle'])] provides the handler
        self::assertArrayHasKey('handler-class-listener', $captured);
        self::assertSame(TestListenerClass::class, $captured['handler-class-listener']->handler?->class ?? null);
        self::assertSame('handle', $captured['handler-class-listener']->handler?->method ?? null);
    }

    // -----------------------------------------------------------------------
    // buildListenerExpr null-handler branch — tested via anonymous subclass
    // -----------------------------------------------------------------------

    public function testBuildListenerExprWithNullHandlerProducesNullConstFetch(): void
    {
        $reader = new class extends ListenerAttributeReader {
            public function callBuildListenerExpr(ListenerData $data): Expr
            {
                return $this->buildListenerExpr($data);
            }
        };

        /** @var class-string $eventId */
        $eventId = TestListenerClass::class;

        $data = new ListenerData(
            eventId: $eventId,
            name: 'null-handler-listener',
            handler: null,
        );

        $expr = $reader->callBuildListenerExpr($data);

        self::assertNotNull($expr);
    }
}
