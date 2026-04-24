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

namespace Sindri\Tests\Unit\Ast\Result;

use Sindri\Ast\Result\ComponentProviderResult;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ComponentProviderResultTest extends TestCase
{
    public function testDefaultConstructorHasEmptyArrays(): void
    {
        $result = new ComponentProviderResult();

        self::assertSame([], $result->componentProviders);
        self::assertSame([], $result->serviceProviders);
        self::assertSame([], $result->listenerProviders);
        self::assertSame([], $result->cliRouteProviders);
        self::assertSame([], $result->httpRouteProviders);
    }

    public function testMergeDeduplicatesEachList(): void
    {
        $a = new ComponentProviderResult(
            componentProviders: ['CompA', 'CompB'],
            serviceProviders: ['SvcA'],
            listenerProviders: ['LstA'],
            cliRouteProviders: ['CliA'],
            httpRouteProviders: ['HttpA'],
        );

        $b = new ComponentProviderResult(
            componentProviders: ['CompB', 'CompC'],
            serviceProviders: ['SvcA', 'SvcB'],
            listenerProviders: ['LstA', 'LstB'],
            cliRouteProviders: ['CliA', 'CliB'],
            httpRouteProviders: ['HttpA', 'HttpB'],
        );

        $merged = $a->merge($b);

        self::assertSame(['CompA', 'CompB', 'CompC'], $merged->componentProviders);
        self::assertSame(['SvcA', 'SvcB'], $merged->serviceProviders);
        self::assertSame(['LstA', 'LstB'], $merged->listenerProviders);
        self::assertSame(['CliA', 'CliB'], $merged->cliRouteProviders);
        self::assertSame(['HttpA', 'HttpB'], $merged->httpRouteProviders);
    }
}