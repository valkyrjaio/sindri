<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Ast;

use Override;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\ComponentProviderReaderContract;
use Sindri\Ast\Data\Result\ComponentProviderResult;

/**
 * Reads a single ComponentProviderContract source file and extracts the class
 * names from all five provider-list methods.
 *
 * This reader is deliberately flat — it does not recurse into sub-component
 * providers. The orchestrator is responsible for walking the provider tree by
 * resolving the returned class names to file paths (via PSR-4 derivation from
 * the source root) and calling readFile() for each one.
 *
 * All methods and properties are protected to allow easy subclass customization.
 */
class ComponentProviderReader extends AstReader implements ComponentProviderReaderContract
{
    protected const string METHOD_COMPONENT = 'getComponentProviders';
    protected const string METHOD_CONTAINER = 'getContainerProviders';
    protected const string METHOD_EVENT     = 'getEventProviders';
    protected const string METHOD_CLI       = 'getCliProviders';
    protected const string METHOD_HTTP      = 'getHttpProviders';
    protected const string METHOD_GRPC      = 'getGrpcProviders';

    /**
     * @inheritDoc
     */
    #[Override]
    public function readFile(string $filePath): ComponentProviderResult
    {
        $stmts = $this->parseFileToStmts($filePath);

        [$namespace, $stmts] = $this->unwrapNamespace($stmts);

        $useMap    = $this->buildUseMap($stmts);
        $classNode = $this->findClass($stmts);

        if ($classNode === null) {
            return new ComponentProviderResult();
        }

        $methods = $this->indexMethods($classNode);

        return new ComponentProviderResult(
            componentProviders: $this->extractClassListFromValues($methods[self::METHOD_COMPONENT] ?? null, $useMap, $namespace),
            serviceProviders: $this->extractClassListFromValues($methods[self::METHOD_CONTAINER] ?? null, $useMap, $namespace),
            listenerProviders: $this->extractClassListFromValues($methods[self::METHOD_EVENT] ?? null, $useMap, $namespace),
            cliRouteProviders: $this->extractClassListFromValues($methods[self::METHOD_CLI] ?? null, $useMap, $namespace),
            httpRouteProviders: $this->extractClassListFromValues($methods[self::METHOD_HTTP] ?? null, $useMap, $namespace),
            grpcRouteProviders: $this->extractClassListFromValues($methods[self::METHOD_GRPC] ?? null, $useMap, $namespace),
        );
    }
}
