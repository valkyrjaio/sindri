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
use Sindri\Ast\CliRouteParameterReader;
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
use Sindri\Ast\HttpRouteMiddlewareReader;
use Sindri\Ast\HttpRouteParameterReader;
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
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;

final class SindriCommandServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = SindriCommandServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(GenerateDataFromConfigCommand::class, SindriCommandServiceProvider::publishers());
    }

    public function testPublishGenerateDataFromConfigCommand(): void
    {
        $container = $this->container;
        $container->setSingleton(RouteContract::class, self::createStub(RouteContract::class));
        $container->setSingleton(OutputFactoryContract::class, self::createStub(OutputFactoryContract::class));
        $container->setSingleton(ConfigReaderContract::class, new ConfigReader());
        $container->setSingleton(ComponentProviderReaderContract::class, new ComponentProviderReader());
        $container->setSingleton(RouteProviderReaderContract::class, new RouteProviderReader());
        $container->setSingleton(ListenerProviderReaderContract::class, new ListenerProviderReader());
        $container->setSingleton(ServiceProviderReaderContract::class, new ServiceProviderReader());
        $container->setSingleton(CliRouteAttributeReaderContract::class, new CliRouteAttributeReader(parameterReader: new CliRouteParameterReader()));
        $container->setSingleton(HttpRouteAttributeReaderContract::class, new HttpRouteAttributeReader(parameterReader: new HttpRouteParameterReader(), middlewareReader: new HttpRouteMiddlewareReader()));
        $container->setSingleton(ListenerAttributeReaderContract::class, new ListenerAttributeReader());
        $container->setSingleton(ContainerDataFileGeneratorContract::class, new AstContainerDataFileGenerator());
        $container->setSingleton(EventDataFileGeneratorContract::class, new AstEventDataFileGenerator());
        $container->setSingleton(CliDataFileGeneratorContract::class, new AstCliDataFileGenerator());
        $container->setSingleton(HttpDataFileGeneratorContract::class, new AstHttpDataFileGenerator());

        $callback = SindriCommandServiceProvider::publishers()[GenerateDataFromConfigCommand::class];
        $callback($container);

        self::assertInstanceOf(GenerateDataFromConfigCommand::class, $container->getSingleton(GenerateDataFromConfigCommand::class));
    }
}
