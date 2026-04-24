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
use Sindri\Generator\Ast\Cli\AstCliDataFileGenerator;
use Sindri\Generator\Ast\Container\AstContainerDataFileGenerator;
use Sindri\Generator\Ast\Event\AstEventDataFileGenerator;
use Sindri\Generator\Ast\Http\AstHttpDataFileGenerator;
use Sindri\Generator\Cli\Contract\CliDataFileGeneratorContract;
use Sindri\Generator\Container\Contract\ContainerDataFileGeneratorContract;
use Sindri\Generator\Event\Contract\EventDataFileGeneratorContract;
use Sindri\Generator\Http\Contract\HttpDataFileGeneratorContract;
use Sindri\Provider\SindriAstServiceProvider;
use Sindri\Tests\Unit\Abstract\TestCase;
use Valkyrja\Container\Manager\Contract\ContainerContract;

final class SindriAstServiceProviderTest extends TestCase
{
    public function testPublishersReturnsArrayWithTwelveEntries(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertCount(12, $publishers);
    }

    public function testPublishersContainsCliRouteAttributeReaderContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(CliRouteAttributeReaderContract::class, $publishers);
    }

    public function testPublishersContainsComponentProviderReaderContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(ComponentProviderReaderContract::class, $publishers);
    }

    public function testPublishersContainsConfigReaderContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(ConfigReaderContract::class, $publishers);
    }

    public function testPublishersContainsHttpRouteAttributeReaderContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(HttpRouteAttributeReaderContract::class, $publishers);
    }

    public function testPublishersContainsListenerAttributeReaderContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(ListenerAttributeReaderContract::class, $publishers);
    }

    public function testPublishersContainsListenerProviderReaderContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(ListenerProviderReaderContract::class, $publishers);
    }

    public function testPublishersContainsRouteProviderReaderContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(RouteProviderReaderContract::class, $publishers);
    }

    public function testPublishersContainsServiceProviderReaderContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(ServiceProviderReaderContract::class, $publishers);
    }

    public function testPublishersContainsCliDataFileGeneratorContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(CliDataFileGeneratorContract::class, $publishers);
    }

    public function testPublishersContainsContainerDataFileGeneratorContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(ContainerDataFileGeneratorContract::class, $publishers);
    }

    public function testPublishersContainsEventDataFileGeneratorContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(EventDataFileGeneratorContract::class, $publishers);
    }

    public function testPublishersContainsHttpDataFileGeneratorContract(): void
    {
        $publishers = SindriAstServiceProvider::publishers();

        self::assertArrayHasKey(HttpDataFileGeneratorContract::class, $publishers);
    }

    public function testPublishCliRouteAttributeReaderRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                CliRouteAttributeReaderContract::class,
                self::isInstanceOf(CliRouteAttributeReader::class)
            );

        SindriAstServiceProvider::publishCliRouteAttributeReader($container);
    }

    public function testPublishComponentProviderReaderRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                ComponentProviderReaderContract::class,
                self::isInstanceOf(ComponentProviderReader::class)
            );

        SindriAstServiceProvider::publishComponentProviderReader($container);
    }

    public function testPublishConfigReaderRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                ConfigReaderContract::class,
                self::isInstanceOf(ConfigReader::class)
            );

        SindriAstServiceProvider::publishConfigReader($container);
    }

    public function testPublishHttpRouteAttributeReaderRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                HttpRouteAttributeReaderContract::class,
                self::isInstanceOf(HttpRouteAttributeReader::class)
            );

        SindriAstServiceProvider::publishHttpRouteAttributeReader($container);
    }

    public function testPublishListenerAttributeReaderRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                ListenerAttributeReaderContract::class,
                self::isInstanceOf(ListenerAttributeReader::class)
            );

        SindriAstServiceProvider::publishListenerAttributeReader($container);
    }

    public function testPublishListenerProviderReaderRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                ListenerProviderReaderContract::class,
                self::isInstanceOf(ListenerProviderReader::class)
            );

        SindriAstServiceProvider::publishListenerProviderReader($container);
    }

    public function testPublishRouteProviderReaderRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                RouteProviderReaderContract::class,
                self::isInstanceOf(RouteProviderReader::class)
            );

        SindriAstServiceProvider::publishRouteProviderReader($container);
    }

    public function testPublishServiceProviderReaderRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                ServiceProviderReaderContract::class,
                self::isInstanceOf(ServiceProviderReader::class)
            );

        SindriAstServiceProvider::publishServiceProviderReader($container);
    }

    public function testPublishCliDataFileGeneratorRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                CliDataFileGeneratorContract::class,
                self::isInstanceOf(AstCliDataFileGenerator::class)
            );

        SindriAstServiceProvider::publishCliDataFileGenerator($container);
    }

    public function testPublishContainerDataFileGeneratorRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                ContainerDataFileGeneratorContract::class,
                self::isInstanceOf(AstContainerDataFileGenerator::class)
            );

        SindriAstServiceProvider::publishContainerDataFileGenerator($container);
    }

    public function testPublishEventDataFileGeneratorRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                EventDataFileGeneratorContract::class,
                self::isInstanceOf(AstEventDataFileGenerator::class)
            );

        SindriAstServiceProvider::publishEventDataFileGenerator($container);
    }

    public function testPublishHttpDataFileGeneratorRegistersInstance(): void
    {
        $container = $this->createMock(ContainerContract::class);
        $container->expects($this->once())
            ->method('setSingleton')
            ->with(
                HttpDataFileGeneratorContract::class,
                self::isInstanceOf(AstHttpDataFileGenerator::class)
            );

        SindriAstServiceProvider::publishHttpDataFileGenerator($container);
    }
}
