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

use Valkyrja\Http\Routing\Attribute\Route;

class TestNoNsHttpControllerClass
{
    #[Route(path: '/no-ns', name: 'no-ns.route')]
    public function action(): void
    {
    }
}
