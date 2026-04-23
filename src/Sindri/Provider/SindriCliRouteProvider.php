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
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Routing\Provider\Contract\CliRouteProviderContract;
use Valkyrja\Container\Manager\Contract\ContainerContract;

class SindriCliRouteProvider implements CliRouteProviderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function getControllerClasses(): array
    {
        return [
            GenerateDataFromConfigCommand::class,
        ];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getRoutes(): array
    {
        return [];
    }

    /**
     * Handler for the CliGenerateDataFromAstCommand command.
     */
    public static function cliGenerateDataHandler(ContainerContract $container): OutputContract
    {
        return $container->getSingleton(GenerateDataFromConfigCommand::class)->run();
    }
}
