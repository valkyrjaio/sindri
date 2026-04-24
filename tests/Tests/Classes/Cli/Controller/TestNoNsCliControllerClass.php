<?php

// phpcs:ignoreFile

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Valkyrja\Cli\Routing\Attribute\Route;

// Intentionally no namespace — covers the $namespace === '' branch in readFile
class TestNoNsCliControllerClass
{
    #[Route(name: 'no-ns:cli-route', description: 'A CLI route in a no-namespace class')]
    public function testAction(): void
    {
    }
}
