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

namespace Sindri\Tests\Unit\Provider;

use Sindri\Cli\Command\GenerateDataFromConfigCommand;
use Sindri\Provider\SindriCliRouteProvider;
use Sindri\Tests\Unit\Abstract\TestCase;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;

final class SindriCliRouteProviderTest extends TestCase
{
    public function testGetControllerClassesReturnsGenerateDataFromConfigCommand(): void
    {
        $classes = SindriCliRouteProvider::getControllerClasses();

        self::assertSame([GenerateDataFromConfigCommand::class], $classes);
    }

    public function testGetRoutesReturnsEmptyArray(): void
    {
        $routes = SindriCliRouteProvider::getRoutes();

        self::assertSame([], $routes);
    }

    public function testCliGenerateDataHandlerDelegatesToCommand(): void
    {
        $output  = self::createStub(OutputContract::class);
        $command = $this->createMock(GenerateDataFromConfigCommand::class);
        $command->expects($this->once())->method('run')->willReturn($output);

        $container = self::createStub(ContainerContract::class);
        $container->method('getSingleton')->willReturn($command);

        $result = SindriCliRouteProvider::cliGenerateDataHandler($container, self::createStub(RouteContract::class));

        self::assertSame($output, $result);
    }
}
