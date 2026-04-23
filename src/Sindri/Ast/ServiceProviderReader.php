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
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\ClassMethod;
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

        $currentClass = $namespace !== ''
            ? $namespace . '\\' . ($classNode->name?->toString() ?? '')
            : ($classNode->name?->toString() ?? '');

        $publishers = $this->extractPublishersMap($methods[self::METHOD_PUBLISHERS] ?? null, $useMap, $namespace, $currentClass);

        return new ServiceProviderResult(
            serviceClasses: array_keys($publishers),
            publishers: $publishers,
        );
    }

    /**
     * Extract the full publishers map from a `publishers()` method.
     *
     * Expects `return [ServiceId::class => [ProviderClass::class, 'methodName'], ...]`.
     * Entries whose key is not a class-const-fetch or whose value is not a two-element
     * callable array are silently skipped.
     *
     * @param array<string, string> $useMap
     *
     * @return array<class-string, array{0: class-string, 1: string}>
     */
    protected function extractPublishersMap(ClassMethod|null $method, array $useMap, string $namespace, string $currentClass = ''): array
    {
        if ($method === null) {
            return [];
        }

        $array = $this->findReturnedArray($method);

        if ($array === null) {
            return [];
        }

        $map = [];

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $serviceId = $this->classConstFetchToFqn($item->key, $useMap, $namespace);

            if ($serviceId === null) {
                continue;
            }

            if (! $item->value instanceof Array_) {
                continue;
            }

            $handler = $this->extractHandlerFromArray($item->value, $useMap, $namespace, $currentClass);

            if ($handler === null) {
                continue;
            }

            /** @var class-string $providerClass */
            $providerClass = $handler->class;

            $map[$serviceId] = [$providerClass, $handler->method];
        }

        return $map;
    }
}
