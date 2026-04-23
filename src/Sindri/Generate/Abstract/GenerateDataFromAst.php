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

namespace Sindri\Generate\Abstract;

use ReflectionClass;
use ReflectionException;
use Sindri\Ast\CliRouteAttributeReader;
use Sindri\Ast\ComponentProviderReader;
use Sindri\Ast\ConfigReader;
use Sindri\Ast\HttpRouteAttributeReader;
use Sindri\Ast\ListenerAttributeReader;
use Sindri\Ast\ListenerProviderReader;
use Sindri\Ast\Result\ComponentProviderResult;
use Sindri\Ast\Result\ConfigResult;
use Sindri\Ast\RouteProviderReader;
use Sindri\Ast\ServiceProviderReader;
use Sindri\Generator\Ast\Cli\AstCliDataFileGenerator;
use Sindri\Generator\Ast\Container\AstContainerDataFileGenerator;
use Sindri\Generator\Ast\Event\AstEventDataFileGenerator;
use Sindri\Generator\Ast\Http\AstHttpDataFileGenerator;
use Sindri\Generator\Enum\GenerateStatus;
use Valkyrja\Cli\Interaction\Formatter\ErrorFormatter;
use Valkyrja\Cli\Interaction\Formatter\HighlightedTextFormatter;
use Valkyrja\Cli\Interaction\Formatter\SuccessFormatter;
use Valkyrja\Cli\Interaction\Formatter\WarningFormatter;
use Valkyrja\Cli\Interaction\Message\Message;
use Valkyrja\Cli\Interaction\Message\NewLine;
use Valkyrja\Cli\Interaction\Output\Contract\OutputContract;
use Valkyrja\Cli\Interaction\Output\Factory\Contract\OutputFactoryContract;

use function strlen;

abstract class GenerateDataFromAst
{
    public function __construct(
        protected OutputFactoryContract $outputFactory,
        protected string $title = 'Generating Data',
        protected ConfigReader $configReader = new ConfigReader(),
        protected ComponentProviderReader $componentProviderReader = new ComponentProviderReader(),
        protected RouteProviderReader $routeProviderReader = new RouteProviderReader(),
        protected ListenerProviderReader $listenerProviderReader = new ListenerProviderReader(),
        protected ServiceProviderReader $serviceProviderReader = new ServiceProviderReader(),
        protected CliRouteAttributeReader $cliRouteAttributeReader = new CliRouteAttributeReader(),
        protected HttpRouteAttributeReader $httpRouteAttributeReader = new HttpRouteAttributeReader(),
        protected ListenerAttributeReader $listenerAttributeReader = new ListenerAttributeReader(),
    ) {
    }

    /**
     * Generate the data.
     */
    protected function generateData(): OutputContract
    {
        $output = $this->getOutput();
        $config = $this->configReader->readFile($this->getConfigFilePath());

        $providers = $this->walkComponentProviders($config);

        $output = $this->generateContainerData($providers->serviceProviders, $config, $output);
        $output = $this->generateEventData($providers->listenerProviders, $config, $output);
        $output = $this->generateCliData($providers->cliRouteProviders, $config, $output);
        $output = $this->generateHttpData($providers->httpRouteProviders, $config, $output);

        return $output->withAddedMessages(new NewLine());
    }

    /**
     * Get the output.
     */
    protected function getOutput(): OutputContract
    {
        return $this->outputFactory
            ->createOutput()
            ->withAddedMessages(
                new NewLine(),
                new Message("$this->title:", new HighlightedTextFormatter()),
                new NewLine(),
                new NewLine(),
            )
            ->writeMessages();
    }

    /**
     * Walk the component provider tree and collect all provider class lists.
     *
     * Each entry in $config->providers is fully expanded before moving to the
     * next, so the declaration order in the config controls the order providers
     * appear in the result. Already-visited classes are skipped to prevent loops.
     */
    protected function walkComponentProviders(ConfigResult $config): ComponentProviderResult
    {
        $result  = new ComponentProviderResult();
        $visited = [];

        foreach ($config->providers as $providerClass) {
            $result = $result->merge($this->walkProvider($providerClass, $config, $visited));
        }

        return $result;
    }

    /**
     * Recursively expand a single component provider.
     *
     * Sub-components are expanded inline in the order they are declared, then
     * the current provider's own lists are appended. The caller controls load
     * order entirely through the config — this method imposes no additional rules.
     *
     * @param array<string, true> $visited
     */
    protected function walkProvider(string $providerClass, ConfigResult $config, array &$visited): ComponentProviderResult
    {
        if (isset($visited[$providerClass])) {
            return new ComponentProviderResult();
        }

        $visited[$providerClass] = true;

        $filePath = $this->fqnToFilePath($providerClass, $config->namespace, $config->dir);

        if (! is_file($filePath)) {
            return new ComponentProviderResult();
        }

        $providerResult = $this->componentProviderReader->readFile($filePath);

        $aggregated = new ComponentProviderResult();

        foreach ($providerResult->componentProviders as $subProvider) {
            $aggregated = $aggregated->merge($this->walkProvider($subProvider, $config, $visited));
        }

        return $aggregated->merge($providerResult);
    }

    /**
     * Derive a file path from a fully-qualified class name.
     *
     * For classes within the app namespace, uses PSR-4 derivation from $srcDir.
     * For vendor/framework classes outside the app namespace, falls back to
     * ReflectionClass::getFileName() so their publishers() maps can be scanned too.
     */
    protected function fqnToFilePath(string $fqn, string $namespace, string $srcDir): string
    {
        if (str_starts_with($fqn, $namespace . '\\')) {
            $relative = substr($fqn, strlen($namespace) + 1);

            return rtrim($srcDir, '/') . '/' . str_replace('\\', '/', $relative) . '.php';
        }

        try {
            $file = new ReflectionClass($fqn)->getFileName();

            return $file !== false ? $file : '';
        } catch (ReflectionException) {
            return '';
        }
    }

    /**
     * Generate the container data file.
     *
     * Reads each service provider's publishers() map via AST, merges all maps together,
     * and writes a ContainerData subclass containing only deferredCallback with ::class syntax.
     *
     * @param class-string[] $serviceProviders
     */
    protected function generateContainerData(array $serviceProviders, ConfigResult $config, OutputContract $output): OutputContract
    {
        $output = $output->withAddedMessages(
            new Message('Generating Container Data......................'),
        )->writeMessages();

        $publishers = [];

        foreach ($serviceProviders as $providerClass) {
            $filePath = $this->fqnToFilePath($providerClass, $config->namespace, $config->dir);

            if (! is_file($filePath)) {
                continue;
            }

            $result     = $this->serviceProviderReader->readFile($filePath);
            $publishers = [...$publishers, ...$result->publishers];
        }

        $generator = new AstContainerDataFileGenerator(
            directory: $config->dataPath,
            publishers: $publishers,
            namespace: $config->dataNamespace,
            className: 'AppContainerData',
        );

        $status = $generator->generateFile();

        return $this->addMessagesForGenerateStatus($output, $status)
            ->withAddedMessages(new NewLine())
            ->writeMessages();
    }

    /**
     * Generate the event listener data file.
     *
     * @param class-string[] $listenerProviders
     */
    protected function generateEventData(array $listenerProviders, ConfigResult $config, OutputContract $output): OutputContract
    {
        $output = $output->withAddedMessages(
            new Message('Generating Event Data..........................'),
        )->writeMessages();

        $allListeners = [];

        foreach ($listenerProviders as $providerClass) {
            $filePath = $this->fqnToFilePath($providerClass, $config->namespace, $config->dir);

            if (! is_file($filePath)) {
                continue;
            }

            $providerResult = $this->listenerProviderReader->readFile($filePath);

            foreach ($providerResult->listenerClasses as $listenerClass) {
                $listenerPath = $this->fqnToFilePath($listenerClass, $config->namespace, $config->dir);

                if (! is_file($listenerPath)) {
                    continue;
                }

                $attrResult   = $this->listenerAttributeReader->readFile($listenerPath);
                $allListeners = [...$allListeners, ...$attrResult->listeners];
            }
        }

        $generator = new AstEventDataFileGenerator(
            directory: $config->dataPath,
            listeners: $allListeners,
            namespace: $config->dataNamespace,
            className: 'AppEventData',
        );

        $status = $generator->generateFile();

        return $this->addMessagesForGenerateStatus($output, $status)
            ->withAddedMessages(new NewLine())
            ->writeMessages();
    }

    /**
     * Generate the CLI routing data file.
     *
     * @param class-string[] $cliRouteProviders
     */
    protected function generateCliData(array $cliRouteProviders, ConfigResult $config, OutputContract $output): OutputContract
    {
        $output = $output->withAddedMessages(
            new Message('Generating Cli Routes Data.....................'),
        )->writeMessages();

        $allRoutes = [];

        foreach ($cliRouteProviders as $providerClass) {
            $filePath = $this->fqnToFilePath($providerClass, $config->namespace, $config->dir);

            if (! is_file($filePath)) {
                continue;
            }

            $providerResult = $this->routeProviderReader->readFile($filePath);

            foreach ($providerResult->controllerClasses as $controllerClass) {
                $controllerPath = $this->fqnToFilePath($controllerClass, $config->namespace, $config->dir);

                if (! is_file($controllerPath)) {
                    continue;
                }

                $attrResult = $this->cliRouteAttributeReader->readFile($controllerPath);
                $allRoutes  = [...$allRoutes, ...$attrResult->routes];
            }
        }

        $generator = new AstCliDataFileGenerator(
            directory: $config->dataPath,
            routes: $allRoutes,
            namespace: $config->dataNamespace,
            className: 'AppCliRoutingData',
        );

        $status = $generator->generateFile();

        return $this->addMessagesForGenerateStatus($output, $status)
            ->withAddedMessages(new NewLine())
            ->writeMessages();
    }

    /**
     * Generate the HTTP routing data file.
     *
     * @param class-string[] $httpRouteProviders
     */
    protected function generateHttpData(array $httpRouteProviders, ConfigResult $config, OutputContract $output): OutputContract
    {
        $output = $output->withAddedMessages(
            new Message('Generating Http Routes Data....................'),
        )->writeMessages();

        $allRoutes = [];

        foreach ($httpRouteProviders as $providerClass) {
            $filePath = $this->fqnToFilePath($providerClass, $config->namespace, $config->dir);

            if (! is_file($filePath)) {
                continue;
            }

            $providerResult = $this->routeProviderReader->readFile($filePath);

            foreach ($providerResult->controllerClasses as $controllerClass) {
                $controllerPath = $this->fqnToFilePath($controllerClass, $config->namespace, $config->dir);

                if (! is_file($controllerPath)) {
                    continue;
                }

                $attrResult = $this->httpRouteAttributeReader->readFile($controllerPath);
                $allRoutes  = [...$allRoutes, ...$attrResult->routes];
            }
        }

        $generator = new AstHttpDataFileGenerator(
            directory: $config->dataPath,
            routes: $allRoutes,
            namespace: $config->dataNamespace,
            className: 'AppHttpRoutingData',
        );

        $status = $generator->generateFile();

        return $this->addMessagesForGenerateStatus($output, $status)
            ->withAddedMessages(new NewLine())
            ->writeMessages();
    }

    /**
     * Add messages for the generate status.
     */
    protected function addMessagesForGenerateStatus(OutputContract $output, GenerateStatus $status): OutputContract
    {
        $text      = 'Failed';
        $formatter = new ErrorFormatter();

        if ($status === GenerateStatus::SUCCESS) {
            $text      = 'Success';
            $formatter = new SuccessFormatter();
        }

        if ($status === GenerateStatus::SKIPPED) {
            $text      = 'Skipped';
            $formatter = new WarningFormatter();
        }

        return $output->withAddedMessages(
            new Message($text, $formatter),
            new NewLine()
        );
    }

    /**
     * Get the path to the application config file.
     */
    abstract protected function getConfigFilePath(): string;
}
