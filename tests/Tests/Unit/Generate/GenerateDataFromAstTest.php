<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Unit\Generate;

use Override;
use ReflectionClass;
use Sindri\Ast\ConfigReader;
use Sindri\Ast\Data\Result\ComponentProviderResult;
use Sindri\Ast\Data\Result\ConfigResult;
use Sindri\Generate\Abstract\GenerateDataFromAst;
use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Fixtures\Cli\Provider\TestMissingControllerProviderFixture;
use Sindri\Tests\Fixtures\Cli\Provider\TestRouteProviderFixture;
use Sindri\Tests\Fixtures\Event\Provider\TestListenerProviderFixture;
use Sindri\Tests\Fixtures\Event\Provider\TestMissingListenerProviderFixture;
use Sindri\Tests\Fixtures\Http\Provider\TestMissingControllerProviderFixture as HttpTestMissingControllerProviderClass;
use Sindri\Tests\Fixtures\Http\Provider\TestRouteProviderFixture as HttpTestRouteProviderClass;
use Sindri\Tests\Fixtures\Provider\Sub\TestServiceProviderFixture;
use Sindri\Tests\Fixtures\Queue\Provider\TestMissingControllerProviderFixture as QueueMissingControllerProviderFixture;
use Sindri\Tests\Fixtures\Queue\Provider\TestQueueRouteProviderFixture;
use Sindri\Tests\Unit\Abstract\TestCase;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;

use function file_get_contents;
use function glob;
use function mkdir;
use function realpath;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class GenerateDataFromAstTest extends TestCase
{
    // -----------------------------------------------------------------------
    // fqnToFilePath
    // -----------------------------------------------------------------------

    public function testFqnToFilePathForInNamespaceClass(): void
    {
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        $result = $walker->callFqnToFilePath('App\\Provider\\SomeProvider', 'App', '/var/www/src');

        self::assertSame('/var/www/src/Provider/SomeProvider.php', $result);
    }

    public function testFqnToFilePathForOutOfNamespaceInternalClassReturnsEmpty(): void
    {
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        // stdClass is a built-in — ReflectionClass::getFileName() returns false.
        $result = $walker->callFqnToFilePath('stdClass', 'App', '/var/www/src');

        self::assertSame('', $result);
    }

    public function testFqnToFilePathForNonExistentClassReturnsEmptyString(): void
    {
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        $result = $walker->callFqnToFilePath('NonExistent\\Class\\DoesNotExist', 'Other', '/var/www/src');

        self::assertSame('', $result);
    }

    public function testFqnToFilePathForOutOfNamespaceUserClassReturnsFileName(): void
    {
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        // A real userland class outside the namespace → ReflectionClass::getFileName()
        // returns its real path (the file-not-false ternary arm).
        $result = $walker->callFqnToFilePath(self::class, 'Other', '/var/www/src');

        self::assertSame((string) new ReflectionClass(self::class)->getFileName(), $result);
    }

    // -----------------------------------------------------------------------
    // walkProvider
    // -----------------------------------------------------------------------

    public function testWalkProviderWithMissingFileReturnsEmptyResult(): void
    {
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        $config = new ConfigResult(namespace: 'App', dir: '/nonexistent/dir');
        $result = $walker->callWalkProvider('App\\Provider\\Missing', $config);

        self::assertSame([], $result->serviceProviders);
        self::assertSame([], $result->listenerProviders);
        self::assertSame([], $result->cliRouteProviders);
        self::assertSame([], $result->httpRouteProviders);
    }

    public function testWalkProviderSkipsAlreadyVisitedClass(): void
    {
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        $config  = new ConfigResult(namespace: 'App', dir: '/nonexistent/dir');
        $visited = ['App\\Provider\\Missing' => true];

        $result = $walker->callWalkProviderWithVisited('App\\Provider\\Missing', $config, $visited);

        self::assertSame([], $result->componentProviders);
    }

    public function testWalkProviderExpandsRealProviderAndSubProviders(): void
    {
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        /** @var non-empty-string $dir */
        $dir    = realpath(__DIR__ . '/../../Fixtures');
        $config = new ConfigResult(namespace: 'Sindri\\Tests\\Fixtures', dir: $dir);

        $result = $walker->callWalkProvider(
            'Sindri\\Tests\\Fixtures\\Provider\\TestComponentProviderFixture',
            $config,
        );

        self::assertNotEmpty($result->serviceProviders);
    }

    public function testWalkComponentProvidersExpandsConfiguredProviders(): void
    {
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        /** @var non-empty-string $dir */
        $dir    = realpath(__DIR__ . '/../../Fixtures');
        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures',
            dir: $dir,
            providers: ['Sindri\\Tests\\Fixtures\\Provider\\TestComponentProviderFixture'],
        );

        $result = $walker->callWalkComponentProviders($config);

        self::assertNotEmpty($result->serviceProviders);
    }

    // -----------------------------------------------------------------------
    // addMessagesForGenerateStatus
    // -----------------------------------------------------------------------

    public function testAddMessagesForGenerateStatusSuccess(): void
    {
        $output = $this->buildChainableOutputStub();
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        $result = $walker->callAddMessagesForGenerateStatus($output, GenerateStatus::SUCCESS);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testAddMessagesForGenerateStatusSkipped(): void
    {
        $output = $this->buildChainableOutputStub();
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        $result = $walker->callAddMessagesForGenerateStatus($output, GenerateStatus::SKIPPED);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testAddMessagesForGenerateStatusFailure(): void
    {
        $output = $this->buildChainableOutputStub();
        $walker = $this->makeWalker(self::createStub(OutputFactoryContract::class));

        $result = $walker->callAddMessagesForGenerateStatus($output, GenerateStatus::FAILURE);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    // -----------------------------------------------------------------------
    // generateData (full pipeline with empty config)
    // -----------------------------------------------------------------------

    public function testGenerateDataRunsFullPipelineAndReturnsOutput(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $outputFactory->method('createOutput')->willReturn($output);

        $config = new ConfigResult(
            namespace: 'App',
            dir: $tmpDir,
            dataPath: $tmpDir,
            dataNamespace: 'App\\Data',
            providers: [],
        );

        $route = $this->createMock(RouteContract::class);
        $route->expects($this->once())->method('getDescription')->willReturn('Generate Data');
        $route->expects($this->once())->method('getName')->willReturn('generate');

        $walker = new class($config, $outputFactory, $route) extends GenerateDataFromAst {
            public function __construct(
                private readonly ConfigResult $staticConfig,
                OutputFactoryContract $outputFactory,
                RouteContract $route,
            ) {
                parent::__construct(outputFactory: $outputFactory, route: $route);
            }

            #[Override]
            protected function getConfigFilePath(): string
            {
                return '';
            }

            #[Override]
            protected function walkComponentProviders(ConfigResult $config): ComponentProviderResult
            {
                return new ComponentProviderResult();
            }

            public function run(): OutputContract
            {
                // Swap config reader for one that returns our pre-built config
                $this->configReader = new class($this->staticConfig) extends ConfigReader {
                    public function __construct(private readonly ConfigResult $result)
                    {
                    }

                    #[Override]
                    public function readFile(string $filePath): ConfigResult
                    {
                        return $this->result;
                    }
                };

                return $this->generateData();
            }
        };

        $result = $walker->run();

        // Clean up generated files
        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    // -----------------------------------------------------------------------
    // generate*Data with real fixture providers
    // -----------------------------------------------------------------------

    public function testGenerateContainerDataWithRealProvider(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $classesDir */
        $classesDir = realpath(__DIR__ . '/../../Fixtures/Provider/Sub');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures\\Provider\\Sub',
            dir: $classesDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Fixtures\\Provider\\Sub\\Data',
        );

        $result = $walker->callGenerateContainerData(
            [TestServiceProviderFixture::class],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateEventDataWithRealProvider(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $eventDir */
        $eventDir = realpath(__DIR__ . '/../../Fixtures/Event');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures\\Event',
            dir: $eventDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Fixtures\\Event\\Data',
        );

        $result = $walker->callGenerateEventData(
            [TestListenerProviderFixture::class],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateCliDataWithRealProvider(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $cliDir */
        $cliDir = realpath(__DIR__ . '/../../Fixtures/Cli');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures\\Cli',
            dir: $cliDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Fixtures\\Cli\\Data',
        );

        $result = $walker->callGenerateCliData(
            [TestRouteProviderFixture::class],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateHttpDataWithRealProvider(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $httpDir */
        $httpDir = realpath(__DIR__ . '/../../Fixtures/Http');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures\\Http',
            dir: $httpDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Fixtures\\Http\\Data',
        );

        $result = $walker->callGenerateHttpData(
            [HttpTestRouteProviderClass::class],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateContainerDataWithMissingProviderFileSkips(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        $config = new ConfigResult(
            namespace: 'App',
            dir: '/nonexistent',
            dataPath: $tmpDir,
            dataNamespace: 'App\\Data',
        );

        $result = $walker->callGenerateContainerData(
            ['App\\Provider\\Missing'],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateEventDataWithMissingProviderFileSkips(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        $config = new ConfigResult(
            namespace: 'App',
            dir: '/nonexistent',
            dataPath: $tmpDir,
            dataNamespace: 'App\\Data',
        );

        $result = $walker->callGenerateEventData(
            ['App\\Provider\\Missing'],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateEventDataWithMissingListenerClassFileSkips(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $eventDir */
        $eventDir = realpath(__DIR__ . '/../../Fixtures/Event');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures\\Event',
            dir: $eventDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Fixtures\\Event\\Data',
        );

        $result = $walker->callGenerateEventData(
            [TestMissingListenerProviderFixture::class],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateQueueDataWithRealProvider(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $queueDir */
        $queueDir = realpath(__DIR__ . '/../../Fixtures/Queue');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures\\Queue',
            dir: $queueDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Fixtures\\Queue\\Data',
        );

        $result = $walker->callGenerateQueueData(
            [TestQueueRouteProviderFixture::class],
            $config,
            $output,
        );

        $generated = (string) file_get_contents($tmpDir . '/AppQueueRoutingData.php');

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
        // The provider's handler class was walked and its attributed job collected
        self::assertStringContainsString("'SendWelcomeEmail'", $generated);
    }

    public function testGenerateQueueDataWithMissingProviderFileSkips(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        $config = new ConfigResult(
            namespace: 'App',
            dir: '/nonexistent',
            dataPath: $tmpDir,
            dataNamespace: 'App\\Data',
        );

        $result = $walker->callGenerateQueueData(
            ['App\\Provider\\Missing'],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateQueueDataWithMissingControllerClassFileSkips(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $queueDir */
        $queueDir = realpath(__DIR__ . '/../../Fixtures/Queue');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures\\Queue',
            dir: $queueDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Fixtures\\Queue\\Data',
        );

        $result = $walker->callGenerateQueueData(
            [QueueMissingControllerProviderFixture::class],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateCliDataWithMissingProviderFileSkips(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        $config = new ConfigResult(
            namespace: 'App',
            dir: '/nonexistent',
            dataPath: $tmpDir,
            dataNamespace: 'App\\Data',
        );

        $result = $walker->callGenerateCliData(
            ['App\\Provider\\Missing'],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateCliDataWithMissingControllerClassFileSkips(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $cliDir */
        $cliDir = realpath(__DIR__ . '/../../Fixtures/Cli');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures\\Cli',
            dir: $cliDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Fixtures\\Cli\\Data',
        );

        $result = $walker->callGenerateCliData(
            [TestMissingControllerProviderFixture::class],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateHttpDataWithMissingProviderFileSkips(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        $config = new ConfigResult(
            namespace: 'App',
            dir: '/nonexistent',
            dataPath: $tmpDir,
            dataNamespace: 'App\\Data',
        );

        $result = $walker->callGenerateHttpData(
            ['App\\Provider\\Missing'],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testGenerateHttpDataWithMissingControllerClassFileSkips(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = self::createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $httpDir */
        $httpDir = realpath(__DIR__ . '/../../Fixtures/Http');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Fixtures\\Http',
            dir: $httpDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Fixtures\\Http\\Data',
        );

        $result = $walker->callGenerateHttpData(
            [HttpTestMissingControllerProviderClass::class],
            $config,
            $output,
        );

        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildChainableOutputStub(): OutputContract
    {
        $output = self::createStub(OutputContract::class);
        $output->method('withAddedMessages')->willReturnSelf();
        $output->method('writeMessages')->willReturnSelf();

        return $output;
    }

    private function makeWalker(OutputFactoryContract $outputFactory): object
    {
        $route = $this->createMock(RouteContract::class);
        $route->expects($this->never())->method('getDescription');
        $route->expects($this->never())->method('getName');

        return new class($outputFactory, $route) extends GenerateDataFromAst {
            private array $sharedVisitedMap = [];

            #[Override]
            protected function getConfigFilePath(): string
            {
                return '';
            }

            public function callFqnToFilePath(string $fqn, string $namespace, string $srcDir): string
            {
                return $this->fqnToFilePath($fqn, $namespace, $srcDir);
            }

            public function callWalkProvider(string $providerClass, ConfigResult $config): ComponentProviderResult
            {
                $visited = [];

                return $this->walkProvider($providerClass, $config, $visited);
            }

            public function callWalkProviderWithVisited(
                string $providerClass,
                ConfigResult $config,
                array $visited,
            ): ComponentProviderResult {
                return $this->walkProvider($providerClass, $config, $visited);
            }

            public function callWalkComponentProviders(ConfigResult $config): ComponentProviderResult
            {
                return $this->walkComponentProviders($config);
            }

            public function callAddMessagesForGenerateStatus(
                OutputContract $output,
                GenerateStatus $status,
            ): OutputContract {
                return $this->addMessagesForGenerateStatus($output, $status);
            }

            public function callGenerateContainerData(
                array $serviceProviders,
                ConfigResult $config,
                OutputContract $output,
            ): OutputContract {
                return $this->generateContainerData($serviceProviders, $config, $output);
            }

            public function callGenerateEventData(
                array $listenerProviders,
                ConfigResult $config,
                OutputContract $output,
            ): OutputContract {
                return $this->generateEventData($listenerProviders, $config, $output);
            }

            public function callGenerateCliData(
                array $cliRouteProviders,
                ConfigResult $config,
                OutputContract $output,
            ): OutputContract {
                return $this->generateCliData($cliRouteProviders, $config, $output);
            }

            public function callGenerateHttpData(
                array $httpRouteProviders,
                ConfigResult $config,
                OutputContract $output,
            ): OutputContract {
                return $this->generateHttpData($httpRouteProviders, $config, $output);
            }

            public function callGenerateQueueData(
                array $queueRouteProviders,
                ConfigResult $config,
                OutputContract $output,
            ): OutputContract {
                return $this->generateQueueData($queueRouteProviders, $config, $output);
            }
        };
    }
}
