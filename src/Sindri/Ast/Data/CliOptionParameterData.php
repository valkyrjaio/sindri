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

use Sindri\Ast\Data\Contract\CliOptionParameterDataContract;

/**
 * Portable intermediate representation of a CLI option parameter extracted
 * from #[OptionParameter] attributes.
 *
 * Enum values (mode, valueMode) are stored as "FQN::CASE" strings.
 */
readonly class CliOptionParameterData implements CliOptionParameterDataContract
{
    /**
     * @param string   $name             Option name
     * @param string   $description      Option description
     * @param string   $valueDisplayName Display name shown in help text
     * @param string|null $cast          "FQN::CASE" of the Cast value, or null
     * @param string   $defaultValue     Default value string
     * @param string[] $shortNames       Short name aliases
     * @param string[] $validValues      Allowed values
     * @param string   $mode             "FQN::CASE" of the OptionMode enum value
     * @param string   $valueMode        "FQN::CASE" of the OptionValueMode enum value
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $valueDisplayName = '',
        public string|null $cast = null,
        public string $defaultValue = '',
        public array $shortNames = [],
        public array $validValues = [],
        public string $mode = 'Valkyrja\\Cli\\Routing\\Enum\\OptionMode::OPTIONAL',
        public string $valueMode = 'Valkyrja\\Cli\\Routing\\Enum\\OptionValueMode::DEFAULT',
    ) {
    }
}