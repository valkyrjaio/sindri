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

use Sindri\Ast\Data\HttpParameterData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class HttpParameterDataTest extends TestCase
{
    public function testConstructorStoresNameAndRegex(): void
    {
        $data = new HttpParameterData(name: 'id', regex: '[0-9]+');

        self::assertSame('id', $data->name);
        self::assertSame('[0-9]+', $data->regex);
    }

    public function testConstructorDefaultsCastToNull(): void
    {
        $data = new HttpParameterData(name: 'id', regex: '[0-9]+');

        self::assertNull($data->cast);
    }

    public function testConstructorDefaultsIsOptionalToFalse(): void
    {
        $data = new HttpParameterData(name: 'id', regex: '[0-9]+');

        self::assertSame(false, $data->isOptional);
    }

    public function testConstructorDefaultsShouldCaptureToTrue(): void
    {
        $data = new HttpParameterData(name: 'id', regex: '[0-9]+');

        self::assertSame(true, $data->shouldCapture);
    }
}