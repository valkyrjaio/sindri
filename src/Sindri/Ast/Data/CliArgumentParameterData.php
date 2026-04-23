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

namespace Sindri\Ast\Data;

use Sindri\Ast\Data\Contract\CliArgumentParameterDataContract;

/**
 * Portable intermediate representation of a CLI argument parameter extracted
 * from #[ArgumentParameter] attributes.
 *
 * Enum values (mode, valueMode) are stored as "FQN::CASE" strings so the
 * file generator can write them verbatim without evaluating them.
 */
readonly class CliArgumentParameterData implements CliArgumentParameterDataContract
{
    /**
     * @param string      $name        Parameter name
     * @param string      $description Parameter description
     * @param string|null $cast        "FQN::CASE" of the Cast value, or null
     * @param string      $mode        "FQN::CASE" of the ArgumentMode enum value
     * @param string      $valueMode   "FQN::CASE" of the ArgumentValueMode enum value
     */
    public function __construct(
        public string $name,
        public string $description,
        public string|null $cast = null,
        public string $mode = 'Valkyrja\\Cli\\Routing\\Enum\\ArgumentMode::OPTIONAL',
        public string $valueMode = 'Valkyrja\\Cli\\Routing\\Enum\\ArgumentValueMode::DEFAULT',
    ) {
    }
}
