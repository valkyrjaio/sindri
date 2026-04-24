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

namespace Sindri\Ast\Abstract;

use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Data\HandlerData;

/**
 * Base for route attribute readers — provides shared handler resolution logic.
 *
 * Concrete subclasses supply the FQN of their specific #[RouteHandler] attribute
 * via getRouteHandlerAttributeClass(), and this class implements the common
 * updateHandler() that uses it.
 */
abstract class RouteAttributeReader extends AstReader
{
    /**
     * Resolve the handler from #[Route\RouteHandler] or fall back to [CurrentClass::class, methodName].
     *
     * @param array<string, string> $useMap
     */
    protected function updateHandler(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): HandlerData {
        foreach ($this->findAttributesOnNode($method, $this->getRouteHandlerAttributeClass(), $useMap, $namespace) as $attr) {
            $raw = $this->extractExprValue($this->getAttrArg($attr->args, 'handler', 0), $useMap, $namespace, $currentClass);

            if ($raw instanceof HandlerData) {
                return $raw;
            }
        }

        /** @var class-string $currentClass */
        return new HandlerData(class: $currentClass, method: $method->name->toString());
    }

    /**
     * Return the fully-qualified class name of the #[RouteHandler] attribute used by this reader.
     *
     * @return class-string
     */
    abstract protected function getRouteHandlerAttributeClass(): string;
}
