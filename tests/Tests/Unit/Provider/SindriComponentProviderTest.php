<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Unit\Provider;

use Sindri\Provider\SindriAstServiceProvider;
use Sindri\Provider\SindriCliRouteProvider;
use Sindri\Provider\SindriCommandServiceProvider;
use Sindri\Provider\SindriComponentProvider;
use Sindri\Tests\Unit\Abstract\TestCase;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;

final class SindriComponentProviderTest extends TestCase
{
    public function testGetComponentProvidersReturnsEmptyArray(): void
    {
        $app    = self::createStub(ApplicationContract::class);
        $result = new SindriComponentProvider()->getComponentProviders($app);

        self::assertSame([], $result);
    }

    public function testGetContainerProvidersReturnsBothServiceProviders(): void
    {
        $app    = self::createStub(ApplicationContract::class);
        $result = new SindriComponentProvider()->getContainerProviders($app);

        self::assertCount(2, $result);
        self::assertInstanceOf(SindriAstServiceProvider::class, $result[0]);
        self::assertInstanceOf(SindriCommandServiceProvider::class, $result[1]);
    }

    public function testGetEventProvidersReturnsEmptyArray(): void
    {
        $app    = self::createStub(ApplicationContract::class);
        $result = new SindriComponentProvider()->getEventProviders($app);

        self::assertSame([], $result);
    }

    public function testGetCliProvidersReturnsSindriCliRouteProvider(): void
    {
        $app    = self::createStub(ApplicationContract::class);
        $result = new SindriComponentProvider()->getCliProviders($app);

        self::assertCount(1, $result);
        self::assertInstanceOf(SindriCliRouteProvider::class, $result[0]);
    }

    public function testGetHttpProvidersReturnsEmptyArray(): void
    {
        $app    = self::createStub(ApplicationContract::class);
        $result = new SindriComponentProvider()->getHttpProviders($app);

        self::assertSame([], $result);
    }

    public function testGetQueueProvidersReturnsEmptyArray(): void
    {
        $app    = self::createStub(ApplicationContract::class);
        $result = new SindriComponentProvider()->getQueueProviders($app);

        self::assertSame([], $result);
    }
}
