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

use Sindri\Ast\Data\CliArgumentParameterData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class CliArgumentParameterDataTest extends TestCase
{
    public function testConstructorStoresNameAndDescription(): void
    {
        $data = new CliArgumentParameterData(name: 'arg', description: 'desc');

        self::assertSame('arg', $data->name);
        self::assertSame('desc', $data->description);
    }

    public function testConstructorDefaultsCastToNull(): void
    {
        $data = new CliArgumentParameterData(name: 'arg', description: 'desc');

        self::assertNull($data->cast);
    }

    public function testConstructorDefaultsMode(): void
    {
        $data = new CliArgumentParameterData(name: 'arg', description: 'desc');

        self::assertSame('Valkyrja\\Cli\\Routing\\Enum\\ArgumentMode::OPTIONAL', $data->mode);
    }

    public function testConstructorDefaultsValueMode(): void
    {
        $data = new CliArgumentParameterData(name: 'arg', description: 'desc');

        self::assertSame('Valkyrja\\Cli\\Routing\\Enum\\ArgumentValueMode::DEFAULT', $data->valueMode);
    }
}