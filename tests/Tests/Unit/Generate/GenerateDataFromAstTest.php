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
use Sindri\Ast\Result\ComponentProviderResult;
use Sindri\Ast\Result\ConfigResult;
use Sindri\Generate\Abstract\GenerateDataFromAst;
use Sindri\Generator\Enum\GenerateStatus;
use Sindri\Tests\Classes\Cli\Provider\TestMissingControllerProviderClass as CliTestMissingControllerProviderClass;
use Sindri\Tests\Classes\Cli\Provider\TestRouteProviderClass as CliTestRouteProviderClass;
use Sindri\Tests\Classes\Event\Provider\TestListenerProviderClass;
use Sindri\Tests\Classes\Event\Provider\TestMissingListenerProviderClass;
use Sindri\Tests\Classes\Http\Provider\TestMissingControllerProviderClass as HttpTestMissingControllerProviderClass;
use Sindri\Tests\Classes\Http\Provider\TestRouteProviderClass as HttpTestRouteProviderClass;
use Sindri\Tests\Classes\Provider\Sub\TestServiceProviderClass;
use Sindri\Tests\Unit\Abstract\TestCase;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;

final class GenerateDataFromAstTest extends TestCase
{
    // -----------------------------------------------------------------------
    // fqnToFilePath
    // -----------------------------------------------------------------------

    public function testFqnToFilePathForInNamespaceClass(): void
    {
        $walker = $this->makeWalker($this->createStub(OutputFactoryContract::class));

        $result = $walker->callFqnToFilePath('App\\Provider\\SomeProvider', 'App', '/var/www/src');

        self::assertSame('/var/www/src/Provider/SomeProvider.php', $result);
    }

    public function testFqnToFilePathForOutOfNamespaceInternalClassReturnsEmpty(): void
    {
        $walker = $this->makeWalker($this->createStub(OutputFactoryContract::class));

        // stdClass is a built-in — ReflectionClass::getFileName() returns false.
        $result = $walker->callFqnToFilePath('stdClass', 'App', '/var/www/src');

        self::assertSame('', $result);
    }

    public function testFqnToFilePathForNonExistentClassReturnsEmptyString(): void
    {
        $walker = $this->makeWalker($this->createStub(OutputFactoryContract::class));

        $result = $walker->callFqnToFilePath('NonExistent\\Class\\DoesNotExist', 'Other', '/var/www/src');

        self::assertSame('', $result);
    }

    // -----------------------------------------------------------------------
    // walkProvider
    // -----------------------------------------------------------------------

    public function testWalkProviderWithMissingFileReturnsEmptyResult(): void
    {
        $walker = $this->makeWalker($this->createStub(OutputFactoryContract::class));

        $config = new ConfigResult(namespace: 'App', dir: '/nonexistent/dir');
        $result = $walker->callWalkProvider('App\\Provider\\Missing', $config);

        self::assertSame([], $result->serviceProviders);
        self::assertSame([], $result->listenerProviders);
        self::assertSame([], $result->cliRouteProviders);
        self::assertSame([], $result->httpRouteProviders);
    }

    public function testWalkProviderSkipsAlreadyVisitedClass(): void
    {
        $walker = $this->makeWalker($this->createStub(OutputFactoryContract::class));

        $config  = new ConfigResult(namespace: 'App', dir: '/nonexistent/dir');
        $visited = ['App\\Provider\\Missing' => true];

        $result = $walker->callWalkProviderWithVisited('App\\Provider\\Missing', $config, $visited);

        self::assertSame([], $result->componentProviders);
    }

    // -----------------------------------------------------------------------
    // addMessagesForGenerateStatus
    // -----------------------------------------------------------------------

    public function testAddMessagesForGenerateStatusSuccess(): void
    {
        $output = $this->buildChainableOutputStub();
        $walker = $this->makeWalker($this->createStub(OutputFactoryContract::class));

        $result = $walker->callAddMessagesForGenerateStatus($output, GenerateStatus::SUCCESS);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testAddMessagesForGenerateStatusSkipped(): void
    {
        $output = $this->buildChainableOutputStub();
        $walker = $this->makeWalker($this->createStub(OutputFactoryContract::class));

        $result = $walker->callAddMessagesForGenerateStatus($output, GenerateStatus::SKIPPED);

        self::assertInstanceOf(OutputContract::class, $result);
    }

    public function testAddMessagesForGenerateStatusFailure(): void
    {
        $output = $this->buildChainableOutputStub();
        $walker = $this->makeWalker($this->createStub(OutputFactoryContract::class));

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

        $output = $this->buildChainableOutputStub();
        $outputFactory = $this->createStub(OutputFactoryContract::class);
        $outputFactory->method('createOutput')->willReturn($output);

        $config = new ConfigResult(
            namespace: 'App',
            dir: $tmpDir,
            dataPath: $tmpDir,
            dataNamespace: 'App\\Data',
            providers: [],
        );

        $walker = new class($config, $outputFactory) extends GenerateDataFromAst {
            public function __construct(
                private readonly ConfigResult $staticConfig,
                OutputFactoryContract $outputFactory,
            ) {
                parent::__construct(outputFactory: $outputFactory);
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
                $this->configReader = new class($this->staticConfig) extends \Sindri\Ast\ConfigReader {
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
    // Helpers
    // -----------------------------------------------------------------------

    private function buildChainableOutputStub(): OutputContract
    {
        $output = $this->createStub(OutputContract::class);
        $output->method('withAddedMessages')->willReturnSelf();
        $output->method('writeMessages')->willReturnSelf();

        return $output;
    }

    private function makeWalker(OutputFactoryContract $outputFactory): object
    {
        return new class($outputFactory) extends GenerateDataFromAst {
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
        };
    }

    // -----------------------------------------------------------------------
    // generate*Data with real fixture providers
    // -----------------------------------------------------------------------

    public function testGenerateContainerDataWithRealProvider(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . $this->name();
        mkdir($tmpDir);

        $output        = $this->buildChainableOutputStub();
        $outputFactory = $this->createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $classesDir */
        $classesDir = realpath(__DIR__ . '/../../Classes/Provider/Sub');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Classes\\Provider\\Sub',
            dir: $classesDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Classes\\Provider\\Sub\\Data',
        );

        $result = $walker->callGenerateContainerData(
            [TestServiceProviderClass::class],
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $eventDir */
        $eventDir = realpath(__DIR__ . '/../../Classes/Event');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Classes\\Event',
            dir: $eventDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Classes\\Event\\Data',
        );

        $result = $walker->callGenerateEventData(
            [TestListenerProviderClass::class],
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $cliDir */
        $cliDir = realpath(__DIR__ . '/../../Classes/Cli');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Classes\\Cli',
            dir: $cliDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Classes\\Cli\\Data',
        );

        $result = $walker->callGenerateCliData(
            [CliTestRouteProviderClass::class],
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $httpDir */
        $httpDir = realpath(__DIR__ . '/../../Classes/Http');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Classes\\Http',
            dir: $httpDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Classes\\Http\\Data',
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $eventDir */
        $eventDir = realpath(__DIR__ . '/../../Classes/Event');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Classes\\Event',
            dir: $eventDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Classes\\Event\\Data',
        );

        $result = $walker->callGenerateEventData(
            [TestMissingListenerProviderClass::class],
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $cliDir */
        $cliDir = realpath(__DIR__ . '/../../Classes/Cli');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Classes\\Cli',
            dir: $cliDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Classes\\Cli\\Data',
        );

        $result = $walker->callGenerateCliData(
            [CliTestMissingControllerProviderClass::class],
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
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
        $outputFactory = $this->createStub(OutputFactoryContract::class);
        $walker        = $this->makeWalker($outputFactory);

        /** @var non-empty-string $httpDir */
        $httpDir = realpath(__DIR__ . '/../../Classes/Http');

        $config = new ConfigResult(
            namespace: 'Sindri\\Tests\\Classes\\Http',
            dir: $httpDir,
            dataPath: $tmpDir,
            dataNamespace: 'Sindri\\Tests\\Classes\\Http\\Data',
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
}