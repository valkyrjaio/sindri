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

use Sindri\Ast\Result\ConfigResult;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ConfigResultTest extends TestCase
{
    public function testDefaultConstructorHasEmptyStrings(): void
    {
        $result = new ConfigResult();

        self::assertSame('', $result->namespace);
        self::assertSame('', $result->dir);
        self::assertSame('', $result->dataPath);
        self::assertSame('', $result->dataNamespace);
        self::assertSame([], $result->providers);
    }

    public function testExplicitValuesAreStored(): void
    {
        $result = new ConfigResult(
            namespace: 'App',
            dir: '/var/www',
            dataPath: '/var/www/data',
            dataNamespace: 'App\\Data',
            providers: ['App\\Provider'],
        );

        self::assertSame('App', $result->namespace);
        self::assertSame('/var/www', $result->dir);
        self::assertSame('/var/www/data', $result->dataPath);
        self::assertSame('App\\Data', $result->dataNamespace);
        self::assertSame(['App\\Provider'], $result->providers);
    }
}
