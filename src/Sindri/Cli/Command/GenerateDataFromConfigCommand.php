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

namespace Sindri\Cli\Command;

use Override;
use Sindri\Constant\CommandName;
use Sindri\Generate\Abstract\GenerateDataFromAst;
use Sindri\Provider\SindriCliRouteProvider;
use Valkyrja\Cli\Interaction\Message\Contract\MessageContract;
use Valkyrja\Cli\Interaction\Message\Message;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;
use Valkyrja\Cli\Routing\Attribute\ArgumentParameter;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Cli\Routing\Data\Contract\RouteContract;
use Valkyrja\Cli\Routing\Enum\ArgumentMode;

class GenerateDataFromConfigCommand extends GenerateDataFromAst
{
    public function __construct(
        protected RouteContract $route,
        OutputFactoryContract $outputFactory,
    ) {
        parent::__construct(
            outputFactory: $outputFactory,
            title: 'Generating Cli Component Data',
        );
    }

    /**
     * The help text.
     */
    public static function help(): MessageContract
    {
        return new Message('A command to generate all data classes for the Cli component.');
    }

    #[Route(
        name: CommandName::DATA_GENERATE,
        description: 'Generate data for the cli component',
        helpText: [self::class, 'help'],
    )]
    #[RouteHandler([SindriCliRouteProvider::class, 'cliGenerateDataHandler'])]
    #[ArgumentParameter(
        name: 'config',
        description: 'The path to the application config file',
        mode: ArgumentMode::REQUIRED
    )]
    public function run(): OutputContract
    {
        return $this->generateData();
    }

    /**
     * Get the path to the application config file from the CLI argument.
     */
    #[Override]
    protected function getConfigFilePath(): string
    {
        return $this->route->getArgument('config')->getFirstValue();
    }
}
