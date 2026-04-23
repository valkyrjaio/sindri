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

namespace Sindri\Tests\Unit\Generate;

use Override;
use Sindri\Ast\Result\ConfigResult;
use Sindri\Generate\Abstract\GenerateDataFromAst;
use Sindri\Tests\Classes\Provider\Sub\TestOtherServiceProviderClass;
use Sindri\Tests\Classes\Provider\Sub\TestServiceProviderClass;
use Sindri\Tests\Classes\Provider\TestFirstComponentProviderClass;
use Sindri\Tests\Classes\Provider\TestSecondComponentProviderClass;
use Sindri\Tests\Unit\Abstract\TestCase;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;

final class WalkComponentProvidersTest extends TestCase
{
    private static string $providerFixtureDir;

    public static function setUpBeforeClass(): void
    {
        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Provider');

        self::$providerFixtureDir = $path;
    }

    public function testServiceProvidersFollowConfigDeclarationOrder(): void
    {
        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Classes\\Provider',
            dir: self::$providerFixtureDir,
            providers: [
                TestFirstComponentProviderClass::class,
                TestSecondComponentProviderClass::class,
            ],
        );

        $walker = new class($this->createStub(OutputFactoryContract::class)) extends GenerateDataFromAst {
            #[Override]
            protected function getConfigFilePath(): string
            {
                return '';
            }

            public function walk(ConfigResult $config): array
            {
                return $this->walkComponentProviders($config)->serviceProviders;
            }
        };

        $result = $walker->walk($config);

        self::assertSame(
            [TestServiceProviderClass::class, TestOtherServiceProviderClass::class],
            $result,
        );
    }

    public function testServiceProviderOrderIsReversedWhenConfigOrderIsReversed(): void
    {
        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Classes\\Provider',
            dir: self::$providerFixtureDir,
            providers: [
                TestSecondComponentProviderClass::class,
                TestFirstComponentProviderClass::class,
            ],
        );

        $walker = new class($this->createStub(OutputFactoryContract::class)) extends GenerateDataFromAst {
            #[Override]
            protected function getConfigFilePath(): string
            {
                return '';
            }

            public function walk(ConfigResult $config): array
            {
                return $this->walkComponentProviders($config)->serviceProviders;
            }
        };

        $result = $walker->walk($config);

        self::assertSame(
            [TestOtherServiceProviderClass::class, TestServiceProviderClass::class],
            $result,
        );
    }
}
