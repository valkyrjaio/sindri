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
use Sindri\Constant\CommandName;
use Sindri\Generate\Abstract\GenerateDataFromAst;
use Sindri\Generator\Ast\Cli\AstCliDataFileGenerator;
use Sindri\Generator\Ast\Container\AstContainerDataFileGenerator;
use Sindri\Generator\Ast\Event\AstEventDataFileGenerator;
use Sindri\Generator\Ast\Http\AstHttpDataFileGenerator;
use Sindri\Generator\Cli\Contract\CliDataFileGeneratorContract;
use Sindri\Generator\Container\Contract\ContainerDataFileGeneratorContract;
use Sindri\Generator\Event\Contract\EventDataFileGeneratorContract;
use Sindri\Generator\Http\Contract\HttpDataFileGeneratorContract;
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
        protected ConfigReaderContract $configReader = new ConfigReader(),
        protected ComponentProviderReaderContract $componentProviderReader = new ComponentProviderReader(),
        protected RouteProviderReaderContract $routeProviderReader = new RouteProviderReader(),
        protected ListenerProviderReaderContract $listenerProviderReader = new ListenerProviderReader(),
        protected ServiceProviderReaderContract $serviceProviderReader = new ServiceProviderReader(),
        protected CliRouteAttributeReaderContract $cliRouteAttributeReader = new CliRouteAttributeReader(),
        protected HttpRouteAttributeReaderContract $httpRouteAttributeReader = new HttpRouteAttributeReader(),
        protected ListenerAttributeReaderContract $listenerAttributeReader = new ListenerAttributeReader(),
        protected ContainerDataFileGeneratorContract $containerGenerator = new AstContainerDataFileGenerator(),
        protected EventDataFileGeneratorContract $eventGenerator = new AstEventDataFileGenerator(),
        protected CliDataFileGeneratorContract $cliGenerator = new AstCliDataFileGenerator(),
        protected HttpDataFileGeneratorContract $httpGenerator = new AstHttpDataFileGenerator(),
    ) {
        parent::__construct(
            outputFactory: $outputFactory,
            title: 'Generating Component Data From Config',
            configReader: $configReader,
            componentProviderReader: $componentProviderReader,
            routeProviderReader: $routeProviderReader,
            listenerProviderReader: $listenerProviderReader,
            serviceProviderReader: $serviceProviderReader,
            cliRouteAttributeReader: $cliRouteAttributeReader,
            httpRouteAttributeReader: $httpRouteAttributeReader,
            listenerAttributeReader: $listenerAttributeReader,
            containerGenerator: $containerGenerator,
            eventGenerator: $eventGenerator,
            cliGenerator: $cliGenerator,
            httpGenerator: $httpGenerator,
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
