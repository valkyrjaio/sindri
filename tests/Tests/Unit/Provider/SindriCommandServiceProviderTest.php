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

use Sindri\Ast\CliRouteAttributeReader;
use Sindri\Ast\ComponentProviderReader;
use Sindri\Ast\ConfigReader;
use Sindri\Ast\Contract\CliRouteAttributeReaderContract;
use Sindri\Ast\Contract\ComponentProviderReaderContract;
use Sindri\Ast\Contract\ConfigReaderContract;
use Sindri\Ast\Contract\HttpRouteAttributeReaderContract;
use Sindri\Ast\Contract\ListenerAttributeReaderContract;
use Sindri\Ast\Contract\ListenerProviderReaderContract;
use Sindri\Ast\Contract\RouteProviderReaderContract;
use Sindri\Ast\Contract\ServiceProviderReaderContract;
use Sindri\Ast\HttpRouteAttributeReader;
use Sindri\Ast\ListenerAttributeReader;
use Sindri\Ast\ListenerProviderReader;
use Sindri\Ast\RouteProviderReader;
use Sindri\Ast\ServiceProviderReader;
use Sindri\Cli\Command\GenerateDataFromConfigCommand;
use Sindri\Generator\Ast\Cli\AstCliDataFileGenerator;
use Sindri\Generator\Ast\Container\AstContainerDataFileGenerator;
use Sindri\Generator\Ast\Event\AstEventDataFileGenerator;
use Sindri\Generator\Ast\Http\AstHttpDataFileGenerator;
use Sindri\Generator\Cli\Contract\CliDataFileGeneratorContract;
use Sindri\Generator\Container\Contract\ContainerDataFileGeneratorContract;
use Sindri\Generator\Event\Contract\EventDataFileGeneratorContract;
use Sindri\Generator\Http\Contract\HttpDataFileGeneratorContract;
use Sindri\Provider\SindriCommandServiceProvider;
use Sindri\Tests\Unit\Abstract\TestCase;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;

final class SindriCommandServiceProviderTest extends TestCase
{
    public function testPublishersReturnsArrayWithOneEntry(): void
    {
        $publishers = SindriCommandServiceProvider::publishers();

        self::assertCount(1, $publishers);
    }

    public function testPublishersContainsGenerateDataFromConfigCommand(): void
    {
        $publishers = SindriCommandServiceProvider::publishers();

        self::assertArrayHasKey(GenerateDataFromConfigCommand::class, $publishers);
    }

    public function testPublishGenerateDataFromConfigCommandRegistersInstance(): void
    {
        $singletons = [
            RouteContract::class                      => self::createStub(RouteContract::class),
            OutputFactoryContract::class              => self::createStub(OutputFactoryContract::class),
            ConfigReaderContract::class               => new ConfigReader(),
            ComponentProviderReaderContract::class    => new ComponentProviderReader(),
            RouteProviderReaderContract::class        => new RouteProviderReader(),
            ListenerProviderReaderContract::class     => new ListenerProviderReader(),
            ServiceProviderReaderContract::class      => new ServiceProviderReader(),
            CliRouteAttributeReaderContract::class    => new CliRouteAttributeReader(),
            HttpRouteAttributeReaderContract::class   => new HttpRouteAttributeReader(),
            ListenerAttributeReaderContract::class    => new ListenerAttributeReader(),
            ContainerDataFileGeneratorContract::class => new AstContainerDataFileGenerator(),
            EventDataFileGeneratorContract::class     => new AstEventDataFileGenerator(),
            CliDataFileGeneratorContract::class       => new AstCliDataFileGenerator(),
            HttpDataFileGeneratorContract::class      => new AstHttpDataFileGenerator(),
        ];

        $container = $this->createMock(ContainerContract::class);
        $container->method('getSingleton')->willReturnCallback(
            static fn (string $id): object => $singletons[$id]
        );
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                GenerateDataFromConfigCommand::class,
                self::isInstanceOf(GenerateDataFromConfigCommand::class)
            );

        SindriCommandServiceProvider::publishGenerateDataFromConfigCommand($container);
    }
}
