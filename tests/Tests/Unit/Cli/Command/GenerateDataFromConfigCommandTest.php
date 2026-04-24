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

namespace Sindri\Tests\Unit\Cli\Command;

use Override;
use Sindri\Ast\Contract\ConfigReaderContract;
use Sindri\Ast\Result\ConfigResult;
use Sindri\Cli\Command\GenerateDataFromConfigCommand;
use Sindri\Tests\Unit\Abstract\TestCase;
use Valkyrja\Cli\Interaction\Message\Message;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Data\ArgumentParameter;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;

final class GenerateDataFromConfigCommandTest extends TestCase
{
    public function testHelpReturnsMessage(): void
    {
        $help = GenerateDataFromConfigCommand::help();

        self::assertInstanceOf(Message::class, $help);
    }

    public function testHelpMessageIsNonEmpty(): void
    {
        $help = GenerateDataFromConfigCommand::help();

        self::assertNotSame('', $help->getText());
    }

    public function testGetConfigFilePathDelegatesToRouteArgument(): void
    {
        $argument = self::createStub(ArgumentParameter::class);
        $argument->method('getFirstValue')->willReturn('/path/to/config.php');

        $route = self::createStub(RouteContract::class);
        $route->method('getArgument')->willReturn($argument);

        $outputFactory = self::createStub(OutputFactoryContract::class);

        $command = new class($route, $outputFactory) extends GenerateDataFromConfigCommand {
            #[Override]
            public function getConfigFilePath(): string
            {
                return parent::getConfigFilePath();
            }
        };

        $result = $command->getConfigFilePath();

        self::assertSame('/path/to/config.php', $result);
    }

    public function testRunCallsGenerateDataAndReturnsOutput(): void
    {
        $tmpDir = sys_get_temp_dir() . '/sindri_cmd_test_' . uniqid();
        mkdir($tmpDir);

        // A chainable OutputContract stub
        $output = self::createStub(OutputContract::class);
        $output->method('withAddedMessages')->willReturnSelf();
        $output->method('writeMessages')->willReturnSelf();

        $outputFactory = self::createStub(OutputFactoryContract::class);
        $outputFactory->method('createOutput')->willReturn($output);

        // ConfigReader stub returns an empty-providers config pointing at tmp dir
        $configReader = self::createStub(ConfigReaderContract::class);
        $configReader->method('readFile')->willReturn(new ConfigResult(
            namespace: 'App',
            dir: $tmpDir,
            dataPath: $tmpDir,
            dataNamespace: 'App\\Data',
            providers: [],
        ));

        $argument = self::createStub(ArgumentParameter::class);
        $argument->method('getFirstValue')->willReturn('/irrelevant/config.php');

        $route = self::createStub(RouteContract::class);
        $route->method('getArgument')->willReturn($argument);

        $command = new GenerateDataFromConfigCommand(
            route: $route,
            outputFactory: $outputFactory,
            configReader: $configReader,
        );

        $result = $command->run();

        // Clean up generated files
        foreach (glob($tmpDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($tmpDir);

        self::assertInstanceOf(OutputContract::class, $result);
    }
}
