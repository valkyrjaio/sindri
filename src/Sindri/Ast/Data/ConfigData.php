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

use Sindri\Ast\Data\Contract\ConfigDataContract;

/**
 * Portable intermediate representation of application config extracted from
 * a PHP config file via AST (e.g. new Config(...) constructor arguments).
 *
 * Mirrors the shape of Valkyrja\Application\Data\Config without requiring
 * the framework class to be instantiated.
 */
readonly class ConfigData implements ConfigDataContract
{
    /**
     * @param string         $namespace     Application namespace prefix (e.g. "App")
     * @param string         $dir           Application root directory (absolute path)
     * @param string         $dataPath      Relative path where generated data files are written
     * @param string         $dataNamespace PHP namespace for generated data classes
     * @param class-string[] $providers     Top-level component provider FQNs
     */
    public function __construct(
        public string $namespace,
        public string $dir,
        public string $dataPath,
        public string $dataNamespace,
        public array $providers = [],
    ) {
    }
}
