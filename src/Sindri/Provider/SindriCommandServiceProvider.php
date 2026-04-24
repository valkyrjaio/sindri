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

namespace Sindri\Provider;

use Override;
use Sindri\Ast\Contract\CliRouteAttributeReaderContract;
use Sindri\Ast\Contract\ComponentProviderReaderContract;
use Sindri\Ast\Contract\ConfigReaderContract;
use Sindri\Ast\Contract\HttpRouteAttributeReaderContract;
use Sindri\Ast\Contract\ListenerAttributeReaderContract;
use Sindri\Ast\Contract\ListenerProviderReaderContract;
use Sindri\Ast\Contract\RouteProviderReaderContract;
use Sindri\Ast\Contract\ServiceProviderReaderContract;
use Sindri\Cli\Command\GenerateDataFromConfigCommand;
use Sindri\Generator\Cli\Contract\CliDataFileGeneratorContract;
use Sindri\Generator\Container\Contract\ContainerDataFileGeneratorContract;
use Sindri\Generator\Event\Contract\EventDataFileGeneratorContract;
use Sindri\Generator\Http\Contract\HttpDataFileGeneratorContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;

class SindriCommandServiceProvider implements ServiceProviderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function publishers(): array
    {
        return [
            GenerateDataFromConfigCommand::class => [self::class, 'publishGenerateDataFromConfigCommand'],
        ];
    }

    public static function publishGenerateDataFromConfigCommand(ContainerContract $container): void
    {
        $container->setSingleton(
            GenerateDataFromConfigCommand::class,
            new GenerateDataFromConfigCommand(
                route: $container->getSingleton(RouteContract::class),
                outputFactory: $container->getSingleton(OutputFactoryContract::class),
                configReader: $container->getSingleton(ConfigReaderContract::class),
                componentProviderReader: $container->getSingleton(ComponentProviderReaderContract::class),
                routeProviderReader: $container->getSingleton(RouteProviderReaderContract::class),
                listenerProviderReader: $container->getSingleton(ListenerProviderReaderContract::class),
                serviceProviderReader: $container->getSingleton(ServiceProviderReaderContract::class),
                cliRouteAttributeReader: $container->getSingleton(CliRouteAttributeReaderContract::class),
                httpRouteAttributeReader: $container->getSingleton(HttpRouteAttributeReaderContract::class),
                listenerAttributeReader: $container->getSingleton(ListenerAttributeReaderContract::class),
                containerGenerator: $container->getSingleton(ContainerDataFileGeneratorContract::class),
                eventGenerator: $container->getSingleton(EventDataFileGeneratorContract::class),
                cliGenerator: $container->getSingleton(CliDataFileGeneratorContract::class),
                httpGenerator: $container->getSingleton(HttpDataFileGeneratorContract::class),
            )
        );
    }
}
