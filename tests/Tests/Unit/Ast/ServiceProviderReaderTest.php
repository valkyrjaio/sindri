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

namespace Sindri\Tests\Unit\Ast;

use Sindri\Ast\ServiceProviderReader;
use Sindri\Tests\Classes\Provider\Sub\TestOtherServiceClass;
use Sindri\Tests\Classes\Provider\Sub\TestOtherServiceProviderClass;
use Sindri\Tests\Classes\Provider\Sub\TestServiceClass;
use Sindri\Tests\Classes\Provider\Sub\TestServiceProviderClass;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ServiceProviderReaderTest extends TestCase
{
    private static string $fixtureFile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        /** @var non-empty-string $path */
        $path = realpath(__DIR__ . '/../../Classes/Provider/Sub/TestServiceProviderClass.php');

        self::$fixtureFile = $path;
    }

    public function testReadFileExtractsServiceClasses(): void
    {
        $result = (new ServiceProviderReader())->readFile(self::$fixtureFile);

        self::assertSame(
            [TestServiceClass::class, TestOtherServiceClass::class],
            $result->serviceClasses,
        );
    }

    public function testReadFileExtractsSelfClassPublisher(): void
    {
        $result = (new ServiceProviderReader())->readFile(self::$fixtureFile);

        self::assertSame(
            [TestServiceProviderClass::class, 'publishTestService'],
            $result->publishers[TestServiceClass::class],
        );
    }

    public function testReadFileExtractsExplicitClassPublisher(): void
    {
        $result = (new ServiceProviderReader())->readFile(self::$fixtureFile);

        self::assertSame(
            [TestOtherServiceProviderClass::class, 'publishTestOtherService'],
            $result->publishers[TestOtherServiceClass::class],
        );
    }
}