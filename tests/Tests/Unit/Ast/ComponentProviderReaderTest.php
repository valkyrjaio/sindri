<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Unit\Ast;

use Sindri\Ast\ComponentProviderReader;
use Sindri\Tests\Fixtures\Grpc\Provider\TestGrpcRouteProviderFixture;
use Sindri\Tests\Fixtures\Provider\Sub\TestServiceProviderFixture;
use Sindri\Tests\Fixtures\Provider\Sub\TestSubComponentProviderFixture;
use Sindri\Tests\Unit\Abstract\TestCase;

use function file_put_contents;
use function realpath;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ComponentProviderReaderTest extends TestCase
{
    private static string $fixtureFile;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Fixtures/Provider/TestComponentProviderFixture.php');

        self::$fixtureFile = $path;
    }

    public function testReadFileExtractsComponentProviders(): void
    {
        $result = new ComponentProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([TestSubComponentProviderFixture::class], $result->componentProviders);
    }

    public function testReadFileExtractsServiceProviders(): void
    {
        $result = new ComponentProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([TestServiceProviderFixture::class], $result->serviceProviders);
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

    public function testReadFileExtractsEmptyGrpcRouteProviders(): void
    {
        $result = new ComponentProviderReader()->readFile(self::$fixtureFile);

        self::assertSame([], $result->grpcRouteProviders);
    }

    public function testReadFileExtractsGrpcRouteProviders(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sindri_test') . '.php';

        file_put_contents($tmpFile, <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Sindri\Tests\Tmp;

            use Sindri\Tests\Fixtures\Grpc\Provider\TestGrpcRouteProviderFixture;
            use Valkyrja\Application\Kernel\Contract\ApplicationContract;
            use Valkyrja\Application\Provider\Abstract\ComponentProvider;

            final class TmpComponentProvider extends ComponentProvider
            {
                public function getGrpcProviders(ApplicationContract $app): array
                {
                    return [new TestGrpcRouteProviderFixture()];
                }
            }
            PHP);

        $result = new ComponentProviderReader()->readFile($tmpFile);

        @unlink($tmpFile);

        self::assertSame([TestGrpcRouteProviderFixture::class], $result->grpcRouteProviders);
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
        self::assertSame([], $result->grpcRouteProviders);
    }
}
