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
use Sindri\Ast\Contract\ListenerProviderReaderContract;
use Sindri\Ast\Result\ListenerProviderResult;

/**
 * Reads a single ListenerProviderContract source file and extracts listener data.
 *
 * Two sources of listeners are read:
 *
 *   getListenerClasses() — returns class names of attributed listener classes;
 *                          a subsequent attribute-reader pass will scan each class
 *                          for #[Listener] attributes and build full data objects.
 *
 *   getListeners()       — returns manually-defined ListenerContract objects;
 *                          their exact shapes are preserved as-is.
 *                          (AST parsing of new-expression arguments to reconstruct
 *                          these objects is a planned next step.)
 *
 * All methods and properties are protected to allow easy subclass customization.
 */
class ListenerProviderReader extends AstReader implements ListenerProviderReaderContract
{
    protected const string METHOD_LISTENER_CLASSES = 'getListenerClasses';
    protected const string METHOD_LISTENERS        = 'getListeners';

    /**
     * @inheritDoc
     */
    #[Override]
    public function readFile(string $filePath): ListenerProviderResult
    {
        $stmts = $this->parseFileToStmts($filePath);

        [$namespace, $stmts] = $this->unwrapNamespace($stmts);

        $useMap    = $this->buildUseMap($stmts);
        $classNode = $this->findClass($stmts);

        if ($classNode === null) {
            return new ListenerProviderResult();
        }

        $methods = $this->indexMethods($classNode);

        return new ListenerProviderResult(
            listenerClasses: $this->extractClassListFromValues($methods[self::METHOD_LISTENER_CLASSES] ?? null, $useMap, $namespace),
            listeners: $this->extractListeners($methods[self::METHOD_LISTENERS] ?? null, $useMap, $namespace),
        );
    }

    /**
     * Extract manually-defined listener objects from the getListeners() method.
     *
     * Currently returns an empty array. Parsing new-expression arguments to
     * reconstruct ListenerContract objects from their AST representation is a
     * planned next step.
     *
     * @param array<string, string> $useMap
     *
     * @return list<object>
     */
    protected function extractListeners(mixed $method, array $useMap, string $namespace): array
    {
        return [];
    }
}