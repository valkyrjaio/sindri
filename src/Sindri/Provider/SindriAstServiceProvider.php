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
use Sindri\Ast\CliRouteAttributeReader;
use Sindri\Ast\CliRouteParameterReader;
use Sindri\Ast\ComponentProviderReader;
use Sindri\Ast\ConfigReader;
use Sindri\Ast\Contract\CliRouteAttributeReaderContract;
use Sindri\Ast\Contract\CliRouteParameterReaderContract;
use Sindri\Ast\Contract\ComponentProviderReaderContract;
use Sindri\Ast\Contract\ConfigReaderContract;
use Sindri\Ast\Contract\HttpRouteAttributeReaderContract;
use Sindri\Ast\Contract\HttpRouteMiddlewareReaderContract;
use Sindri\Ast\Contract\HttpRouteParameterReaderContract;
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
use Sindri\Generator\Ast\Cli\AstCliDataFileGenerator;
use Sindri\Generator\Ast\Container\AstContainerDataFileGenerator;
use Sindri\Generator\Ast\Event\AstEventDataFileGenerator;
use Sindri\Generator\Ast\Http\AstHttpDataFileGenerator;
use Sindri\Generator\Cli\Contract\CliDataFileGeneratorContract;
use Sindri\Generator\Container\Contract\ContainerDataFileGeneratorContract;
use Sindri\Generator\Event\Contract\EventDataFileGeneratorContract;
use Sindri\Generator\Http\Contract\HttpDataFileGeneratorContract;
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
            CliRouteAttributeReaderContract::class    => [self::class, 'publishCliRouteAttributeReader'],
            ComponentProviderReaderContract::class    => [self::class, 'publishComponentProviderReader'],
            ConfigReaderContract::class               => [self::class, 'publishConfigReader'],
            CliRouteParameterReaderContract::class    => [self::class, 'publishCliRouteParameterReader'],
            HttpRouteMiddlewareReaderContract::class  => [self::class, 'publishHttpRouteMiddlewareReader'],
            HttpRouteParameterReaderContract::class   => [self::class, 'publishHttpRouteParameterReader'],
            HttpRouteAttributeReaderContract::class   => [self::class, 'publishHttpRouteAttributeReader'],
            ListenerAttributeReaderContract::class    => [self::class, 'publishListenerAttributeReader'],
            ListenerProviderReaderContract::class     => [self::class, 'publishListenerProviderReader'],
            RouteProviderReaderContract::class        => [self::class, 'publishRouteProviderReader'],
            ServiceProviderReaderContract::class      => [self::class, 'publishServiceProviderReader'],
            CliDataFileGeneratorContract::class       => [self::class, 'publishCliDataFileGenerator'],
            ContainerDataFileGeneratorContract::class => [self::class, 'publishContainerDataFileGenerator'],
            EventDataFileGeneratorContract::class     => [self::class, 'publishEventDataFileGenerator'],
            HttpDataFileGeneratorContract::class      => [self::class, 'publishHttpDataFileGenerator'],
        ];
    }

    public static function publishCliRouteAttributeReader(ContainerContract $container): void
    {
        $container->setSingleton(
            CliRouteAttributeReaderContract::class,
            new CliRouteAttributeReader(
                parameterReader: $container->getSingleton(CliRouteParameterReaderContract::class),
            )
        );
    }

    public static function publishComponentProviderReader(ContainerContract $container): void
    {
        $container->setSingleton(
            ComponentProviderReaderContract::class,
            new ComponentProviderReader()
        );
    }

    public static function publishConfigReader(ContainerContract $container): void
    {
        $container->setSingleton(
            ConfigReaderContract::class,
            new ConfigReader()
        );
    }

    public static function publishCliRouteParameterReader(ContainerContract $container): void
    {
        $container->setSingleton(
            CliRouteParameterReaderContract::class,
            new CliRouteParameterReader()
        );
    }

    public static function publishHttpRouteMiddlewareReader(ContainerContract $container): void
    {
        $container->setSingleton(
            HttpRouteMiddlewareReaderContract::class,
            new HttpRouteMiddlewareReader()
        );
    }

    public static function publishHttpRouteParameterReader(ContainerContract $container): void
    {
        $container->setSingleton(
            HttpRouteParameterReaderContract::class,
            new HttpRouteParameterReader()
        );
    }

    public static function publishHttpRouteAttributeReader(ContainerContract $container): void
    {
        $container->setSingleton(
            HttpRouteAttributeReaderContract::class,
            new HttpRouteAttributeReader(
                parameterReader: $container->getSingleton(HttpRouteParameterReaderContract::class),
                middlewareReader: $container->getSingleton(HttpRouteMiddlewareReaderContract::class),
            )
        );
    }

    public static function publishListenerAttributeReader(ContainerContract $container): void
    {
        $container->setSingleton(
            ListenerAttributeReaderContract::class,
            new ListenerAttributeReader()
        );
    }

    public static function publishListenerProviderReader(ContainerContract $container): void
    {
        $container->setSingleton(
            ListenerProviderReaderContract::class,
            new ListenerProviderReader()
        );
    }

    public static function publishRouteProviderReader(ContainerContract $container): void
    {
        $container->setSingleton(
            RouteProviderReaderContract::class,
            new RouteProviderReader()
        );
    }

    public static function publishServiceProviderReader(ContainerContract $container): void
    {
        $container->setSingleton(
            ServiceProviderReaderContract::class,
            new ServiceProviderReader()
        );
    }

    public static function publishCliDataFileGenerator(ContainerContract $container): void
    {
        $container->setSingleton(
            CliDataFileGeneratorContract::class,
            new AstCliDataFileGenerator()
        );
    }

    public static function publishContainerDataFileGenerator(ContainerContract $container): void
    {
        $container->setSingleton(
            ContainerDataFileGeneratorContract::class,
            new AstContainerDataFileGenerator()
        );
    }

    public static function publishEventDataFileGenerator(ContainerContract $container): void
    {
        $container->setSingleton(
            EventDataFileGeneratorContract::class,
            new AstEventDataFileGenerator()
        );
    }

    public static function publishHttpDataFileGenerator(ContainerContract $container): void
    {
        $container->setSingleton(
            HttpDataFileGeneratorContract::class,
            new AstHttpDataFileGenerator()
        );
    }
}
