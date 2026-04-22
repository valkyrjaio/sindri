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

namespace Sindri\Ast;

use Override;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\ServiceProviderReaderContract;
use Sindri\Ast\Result\ServiceProviderResult;

/**
 * Reads a single ServiceProviderContract source file and extracts the class
 * names of every service it publishes.
 *
 * Service class names are the keys of the `publishers()` return array:
 *
 *   return [
 *       SomeService::class => [self::class, 'publishSomeService'],
 *   ];
 *
 * All methods and properties are protected to allow easy subclass customization.
 */
class ServiceProviderReader extends AstReader implements ServiceProviderReaderContract
{
    protected const string METHOD_PUBLISHERS = 'publishers';

    /**
     * @inheritDoc
     */
    #[Override]
    public function readFile(string $filePath): ServiceProviderResult
    {
        $stmts = $this->parseFileToStmts($filePath);

        [$namespace, $stmts] = $this->unwrapNamespace($stmts);

        $useMap    = $this->buildUseMap($stmts);
        $classNode = $this->findClass($stmts);

        if ($classNode === null) {
            return new ServiceProviderResult();
        }

        $methods = $this->indexMethods($classNode);

        return new ServiceProviderResult(
            serviceClasses: $this->extractClassListFromKeys($methods[self::METHOD_PUBLISHERS] ?? null, $useMap, $namespace),
        );
    }
}