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
use Sindri\Provider\SindriAstServiceProvider;
use Valkyrja\PhpUnit\Abstract\ServiceProviderTestCase;

final class SindriAstServiceProviderTest extends ServiceProviderTestCase
{
    /** @inheritDoc */
    protected static string $provider = SindriAstServiceProvider::class;

    public function testExpectedPublishers(): void
    {
        self::assertArrayHasKey(CliRouteAttributeReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(ComponentProviderReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(ConfigReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(CliRouteParameterReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(HttpRouteMiddlewareReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(HttpRouteParameterReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(HttpRouteAttributeReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(ListenerAttributeReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(ListenerProviderReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(RouteProviderReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(ServiceProviderReaderContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(CliDataFileGeneratorContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(ContainerDataFileGeneratorContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(EventDataFileGeneratorContract::class, SindriAstServiceProvider::publishers());
        self::assertArrayHasKey(HttpDataFileGeneratorContract::class, SindriAstServiceProvider::publishers());
    }

    public function testPublishCliRouteAttributeReader(): void
    {
        $this->container->setSingleton(CliRouteParameterReaderContract::class, new CliRouteParameterReader());

        $callback = SindriAstServiceProvider::publishers()[CliRouteAttributeReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(CliRouteAttributeReader::class, $this->container->getSingleton(CliRouteAttributeReaderContract::class));
    }

    public function testPublishComponentProviderReader(): void
    {
        $callback = SindriAstServiceProvider::publishers()[ComponentProviderReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(ComponentProviderReader::class, $this->container->getSingleton(ComponentProviderReaderContract::class));
    }

    public function testPublishConfigReader(): void
    {
        $callback = SindriAstServiceProvider::publishers()[ConfigReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(ConfigReader::class, $this->container->getSingleton(ConfigReaderContract::class));
    }

    public function testPublishCliRouteParameterReader(): void
    {
        $callback = SindriAstServiceProvider::publishers()[CliRouteParameterReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(CliRouteParameterReader::class, $this->container->getSingleton(CliRouteParameterReaderContract::class));
    }

    public function testPublishHttpRouteMiddlewareReader(): void
    {
        $callback = SindriAstServiceProvider::publishers()[HttpRouteMiddlewareReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(HttpRouteMiddlewareReader::class, $this->container->getSingleton(HttpRouteMiddlewareReaderContract::class));
    }

    public function testPublishHttpRouteParameterReader(): void
    {
        $callback = SindriAstServiceProvider::publishers()[HttpRouteParameterReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(HttpRouteParameterReader::class, $this->container->getSingleton(HttpRouteParameterReaderContract::class));
    }

    public function testPublishHttpRouteAttributeReader(): void
    {
        $this->container->setSingleton(HttpRouteParameterReaderContract::class, new HttpRouteParameterReader());
        $this->container->setSingleton(HttpRouteMiddlewareReaderContract::class, new HttpRouteMiddlewareReader());

        $callback = SindriAstServiceProvider::publishers()[HttpRouteAttributeReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(HttpRouteAttributeReader::class, $this->container->getSingleton(HttpRouteAttributeReaderContract::class));
    }

    public function testPublishListenerAttributeReader(): void
    {
        $callback = SindriAstServiceProvider::publishers()[ListenerAttributeReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(ListenerAttributeReader::class, $this->container->getSingleton(ListenerAttributeReaderContract::class));
    }

    public function testPublishListenerProviderReader(): void
    {
        $callback = SindriAstServiceProvider::publishers()[ListenerProviderReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(ListenerProviderReader::class, $this->container->getSingleton(ListenerProviderReaderContract::class));
    }

    public function testPublishRouteProviderReader(): void
    {
        $callback = SindriAstServiceProvider::publishers()[RouteProviderReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(RouteProviderReader::class, $this->container->getSingleton(RouteProviderReaderContract::class));
    }

    public function testPublishServiceProviderReader(): void
    {
        $callback = SindriAstServiceProvider::publishers()[ServiceProviderReaderContract::class];
        $callback($this->container);

        self::assertInstanceOf(ServiceProviderReader::class, $this->container->getSingleton(ServiceProviderReaderContract::class));
    }

    public function testPublishCliDataFileGenerator(): void
    {
        $callback = SindriAstServiceProvider::publishers()[CliDataFileGeneratorContract::class];
        $callback($this->container);

        self::assertInstanceOf(AstCliDataFileGenerator::class, $this->container->getSingleton(CliDataFileGeneratorContract::class));
    }

    public function testPublishContainerDataFileGenerator(): void
    {
        $callback = SindriAstServiceProvider::publishers()[ContainerDataFileGeneratorContract::class];
        $callback($this->container);

        self::assertInstanceOf(AstContainerDataFileGenerator::class, $this->container->getSingleton(ContainerDataFileGeneratorContract::class));
    }

    public function testPublishEventDataFileGenerator(): void
    {
        $callback = SindriAstServiceProvider::publishers()[EventDataFileGeneratorContract::class];
        $callback($this->container);

        self::assertInstanceOf(AstEventDataFileGenerator::class, $this->container->getSingleton(EventDataFileGeneratorContract::class));
    }

    public function testPublishHttpDataFileGenerator(): void
    {
        $callback = SindriAstServiceProvider::publishers()[HttpDataFileGeneratorContract::class];
        $callback($this->container);

        self::assertInstanceOf(AstHttpDataFileGenerator::class, $this->container->getSingleton(HttpDataFileGeneratorContract::class));
    }
}
