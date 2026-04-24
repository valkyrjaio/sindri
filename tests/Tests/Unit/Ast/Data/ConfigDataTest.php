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

namespace Sindri\Tests\Unit\Ast\Data;

use Sindri\Ast\Data\ConfigData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ConfigDataTest extends TestCase
{
    public function testConstructorStoresAllProperties(): void
    {
        $data = new ConfigData(
            namespace: 'App',
            dir: '/var/www/app',
            dataPath: '/var/www/app/data',
            dataNamespace: 'App\\Data',
            providers: ['App\\Provider\\AppProvider'],
        );

        self::assertSame('App', $data->namespace);
        self::assertSame('/var/www/app', $data->dir);
        self::assertSame('/var/www/app/data', $data->dataPath);
        self::assertSame('App\\Data', $data->dataNamespace);
        self::assertSame(['App\\Provider\\AppProvider'], $data->providers);
    }

    public function testConstructorDefaultsProvidersToEmptyArray(): void
    {
        $data = new ConfigData(
            namespace: 'App',
            dir: '/var/www/app',
            dataPath: '/var/www/app/data',
            dataNamespace: 'App\\Data',
        );

        self::assertSame([], $data->providers);
    }
}
