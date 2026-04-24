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
        $app    = $this->createStub(ApplicationContract::class);
        $result = SindriComponentProvider::getComponentProviders($app);

        self::assertSame([], $result);
    }

    public function testGetContainerProvidersReturnsBothServiceProviders(): void
    {
        $app    = $this->createStub(ApplicationContract::class);
        $result = SindriComponentProvider::getContainerProviders($app);

        self::assertSame([SindriAstServiceProvider::class, SindriCommandServiceProvider::class], $result);
    }

    public function testGetEventProvidersReturnsEmptyArray(): void
    {
        $app    = $this->createStub(ApplicationContract::class);
        $result = SindriComponentProvider::getEventProviders($app);

        self::assertSame([], $result);
    }

    public function testGetCliProvidersReturnsSindriCliRouteProvider(): void
    {
        $app    = $this->createStub(ApplicationContract::class);
        $result = SindriComponentProvider::getCliProviders($app);

        self::assertSame([SindriCliRouteProvider::class], $result);
    }

    public function testGetHttpProvidersReturnsEmptyArray(): void
    {
        $app    = $this->createStub(ApplicationContract::class);
        $result = SindriComponentProvider::getHttpProviders($app);

        self::assertSame([], $result);
    }
}