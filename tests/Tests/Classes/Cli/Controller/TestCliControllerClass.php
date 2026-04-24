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

namespace Sindri\Tests\Classes\Cli\Controller;

use Valkyrja\Cli\Routing\Attribute\ArgumentParameter;
use Valkyrja\Cli\Routing\Attribute\OptionParameter;
use Valkyrja\Cli\Routing\Attribute\Route;

class TestCliControllerClass
{
    #[Route(name: 'test:cli-route', description: 'A test CLI route')]
    #[ArgumentParameter(name: 'arg1', description: 'First argument')]
    #[OptionParameter(name: 'opt1', description: 'First option', shortNames: ['o'])]
    public function testAction(): void
    {
    }
}