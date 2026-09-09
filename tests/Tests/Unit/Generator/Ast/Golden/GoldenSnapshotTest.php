<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Unit\Generator\Ast\Golden;

use PhpParser\Node\Scalar\String_;
use Sindri\Ast\Data\HttpParameterData;
use Sindri\Ast\Data\HttpRouteData;
use Sindri\Generator\Ast\Cli\AstCliDataFileGenerator;
use Sindri\Generator\Ast\Container\AstContainerDataFileGenerator;
use Sindri\Generator\Ast\Event\AstEventDataFileGenerator;
use Sindri\Generator\Ast\Http\AstHttpDataFileGenerator;
use Sindri\Generator\Ast\Queue\AstQueueDataFileGenerator;
use Sindri\Tests\Unit\Abstract\TestCase;

use function file_get_contents;
use function file_put_contents;
use function getenv;
use function sys_get_temp_dir;
use function unlink;

/**
 * Full-output golden/snapshot tests for the Ast data-file generators.
 *
 * Unlike the per-generator unit tests (which assert individual substrings such as
 * `routes:` or a single route key), these pin the ENTIRE emitted source against a
 * committed golden file, so any change to the generated shape — spacing, ordering,
 * imports, the fully-qualified references, the closure/`::class` wrappers — is
 * caught and must be an intentional golden update.
 *
 * The inputs exercise the meaningful structure: multiple HTTP routes including a
 * dynamic `/users/{id}` and a GET/POST split (so `routes`, `paths`, `dynamicPaths`
 * and `regexes` are all populated); multiple CLI commands; multiple container
 * publishers; multiple event listeners.
 *
 * To refresh the goldens after an intentional generator change, run this suite with
 * `GOLDEN_UPDATE=1` set — each `tests/Tests/Unit/Generator/Ast/Golden/golden/*.golden`
 * is rewritten from the matching generator output — then review and commit the new
 * snapshots. Coverage is unaffected either way: `phpunit.xml.dist` scopes `<source>`
 * to `src/`, so a branch in this test file is never measured.
 */
final class GoldenSnapshotTest extends TestCase
{
    public function testHttpRoutingDataMatchesGolden(): void
    {
        $routes = [
            'users.index' => new String_('users-index-expr'),
            'users.show'  => new String_('users-show-expr'),
            'users.store' => new String_('users-store-expr'),
        ];

        $routeData = [
            'users.index' => new HttpRouteData(
                path: '/users',
                name: 'users.index',
                requestMethods: ['Valkyrja\\Http\\Message\\Enum\\RequestMethod::GET'],
                isDynamic: false,
            ),
            'users.show'  => new HttpRouteData(
                path: '/users/{id}',
                name: 'users.show',
                requestMethods: ['Valkyrja\\Http\\Message\\Enum\\RequestMethod::GET'],
                isDynamic: true,
                parameters: [new HttpParameterData(name: 'id', regex: '[0-9]+')],
            ),
            'users.store' => new HttpRouteData(
                path: '/users',
                name: 'users.store',
                requestMethods: ['Valkyrja\\Http\\Message\\Enum\\RequestMethod::POST'],
                isDynamic: false,
            ),
        ];

        $className = 'AppHttpRoutingData';
        $filePath  = sys_get_temp_dir() . '/' . $className . '.php';
        @unlink($filePath);

        $generator = new AstHttpDataFileGenerator();
        $generator->generateFile(
            directory: sys_get_temp_dir(),
            className: $className,
            namespace: 'App\\Data',
            routes: $routes,
            routeData: $routeData,
        );

        $actual = (string) file_get_contents($filePath);
        @unlink($filePath);

        $this->assertGolden($actual, $className);
    }

    public function testCliRoutingDataMatchesGolden(): void
    {
        $routes = [
            'greet'    => new String_('greet-expr'),
            'farewell' => new String_('farewell-expr'),
        ];

        $className = 'AppCliRoutingData';
        $filePath  = sys_get_temp_dir() . '/' . $className . '.php';
        @unlink($filePath);

        $generator = new AstCliDataFileGenerator();
        $generator->generateFile(
            directory: sys_get_temp_dir(),
            className: $className,
            namespace: 'App\\Data',
            routes: $routes,
        );

        $actual = (string) file_get_contents($filePath);
        @unlink($filePath);

        $this->assertGolden($actual, $className);
    }

    public function testQueueRoutingDataMatchesGolden(): void
    {
        $routes = [
            'SendWelcomeEmail' => new String_('send-welcome-email-expr'),
            'ChargeCard'       => new String_('charge-card-expr'),
        ];

        $className = 'AppQueueRoutingData';
        $filePath  = sys_get_temp_dir() . '/' . $className . '.php';
        @unlink($filePath);

        $generator = new AstQueueDataFileGenerator();
        $generator->generateFile(
            directory: sys_get_temp_dir(),
            className: $className,
            namespace: 'App\\Data',
            routes: $routes,
        );

        $actual = (string) file_get_contents($filePath);
        @unlink($filePath);

        $this->assertGolden($actual, $className);
    }

    public function testContainerDataMatchesGolden(): void
    {
        $publishers = [
            'Fixtures\\Service\\ServiceA' => ['Fixtures\\Provider\\ProviderA', 'publishA'],
            'Fixtures\\Service\\ServiceB' => ['Fixtures\\Provider\\ProviderB', 'publishB'],
        ];

        $className = 'AppContainerData';
        $filePath  = sys_get_temp_dir() . '/' . $className . '.php';
        @unlink($filePath);

        $generator = new AstContainerDataFileGenerator();
        $generator->generateFile(
            directory: sys_get_temp_dir(),
            className: $className,
            namespace: 'App\\Data',
            publishers: $publishers,
        );

        $actual = (string) file_get_contents($filePath);
        @unlink($filePath);

        $this->assertGolden($actual, $className);
    }

    public function testEventDataMatchesGolden(): void
    {
        $listeners = [
            'user.created' => new String_('user-created-expr'),
            'user.deleted' => new String_('user-deleted-expr'),
        ];

        $className = 'AppEventData';
        $filePath  = sys_get_temp_dir() . '/' . $className . '.php';
        @unlink($filePath);

        $generator = new AstEventDataFileGenerator();
        $generator->generateFile(
            directory: sys_get_temp_dir(),
            className: $className,
            namespace: 'App\\Data',
            listeners: $listeners,
        );

        $actual = (string) file_get_contents($filePath);
        @unlink($filePath);

        $this->assertGolden($actual, $className);
    }

    /**
     * Assert the generated source matches the committed golden snapshot, refreshing it first when
     * `GOLDEN_UPDATE=1` is set (mirroring the Java and TypeScript ports' snapshot switch).
     */
    private function assertGolden(string $actual, string $goldenName): void
    {
        $goldenPath = __DIR__ . '/golden/' . $goldenName . '.golden';

        if (getenv('GOLDEN_UPDATE') === '1') {
            file_put_contents($goldenPath, $actual);
        }

        $golden = (string) file_get_contents($goldenPath);

        self::assertSame($golden, $actual);
    }
}
