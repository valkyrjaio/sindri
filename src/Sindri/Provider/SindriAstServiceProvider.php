<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sindri\Provider;

use Override;
use Sindri\Cli\Command\GenerateDataFromConfigCommand;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;
use Valkyrja\Container\Provider\Contract\ServiceProviderContract;

class SindriAstServiceProvider implements ServiceProviderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function publishers(): array
    {
        return [
            GenerateDataFromConfigCommand::class => [self::class, 'publishCliGenerateDataFromAstCommand'],
        ];
    }

    public static function publishCliGenerateDataFromAstCommand(ContainerContract $container): void
    {
        $container->setSingleton(
            GenerateDataFromConfigCommand::class,
            new GenerateDataFromConfigCommand(
                route: $container->getSingleton(RouteContract::class),
                outputFactory: $container->getSingleton(OutputFactoryContract::class),
            )
        );
    }
}
