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

namespace Sindri\Ast\Result;

/**
 * Config values extracted from an application config class file via AST.
 */
readonly class ConfigResult
{
    /**
     * @param non-empty-string   $namespace
     * @param non-empty-string   $dir
     * @param non-empty-string   $dataPath
     * @param non-empty-string   $dataNamespace
     * @param class-string[]     $providers
     */
    public function __construct(
        public string $namespace = '',
        public string $dir = '',
        public string $dataPath = '',
        public string $dataNamespace = '',
        public array $providers = [],
    ) {
    }
}