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

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\ListenerAttributeReaderContract;
use Sindri\Ast\Data\HandlerData;
use Sindri\Ast\Data\ListenerData;
use Sindri\Ast\Result\ListenerAttributeResult;
use Valkyrja\Event\Attribute\Listener;
use Valkyrja\Event\Attribute\ListenerHandler;
use Valkyrja\Event\Data\Listener as ListenerModel;

use function is_string;

/**
 * Scans a listener class file for #[Listener] and #[ListenerHandler] attributes
 * and returns PHP-Parser Expr nodes ready for the data cache generator.
 *
 * Mirrors the logic of the framework's runtime attribute collector but operates
 * entirely on AST without executing any PHP code.
 */
class ListenerAttributeReader extends AstReader implements ListenerAttributeReaderContract
{
    protected const string DEFAULT_HANDLE_METHOD = 'handle';

    public function readFile(string $filePath): ListenerAttributeResult
    {
        $stmts = $this->parseFileToStmts($filePath);

        [$namespace, $innerStmts] = $this->unwrapNamespace($stmts);

        $useMap = $this->buildUseMap($innerStmts);
        $class  = $this->findClass($innerStmts);

        if ($class === null) {
            return new ListenerAttributeResult();
        }

        $currentClass = $namespace !== ''
            ? $namespace . '\\' . ($class->name?->toString() ?? '')
            : ($class->name?->toString() ?? '');

        $listeners = [];

        foreach ($this->findAttributesOnNode($class, Listener::class, $useMap, $namespace) as $attr) {
            $data = $this->buildListenerData($attr->args, $useMap, $namespace, $currentClass, $class, null);

            if ($data !== null) {
                $listeners[$data->name] = $this->buildListenerExpr($data);
            }
        }

        foreach ($class->getMethods() as $method) {
            foreach ($this->findAttributesOnNode($method, Listener::class, $useMap, $namespace) as $attr) {
                $data = $this->buildListenerData($attr->args, $useMap, $namespace, $currentClass, $class, $method);

                if ($data !== null) {
                    $listeners[$data->name] = $this->buildListenerExpr($data);
                }
            }
        }

        return new ListenerAttributeResult(listeners: $listeners);
    }

    /**
     * Collect all attribute arguments for a #[Listener] into a ListenerData.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     */
    protected function buildListenerData(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
        Class_ $class,
        ClassMethod|null $method,
    ): ListenerData|null {
        $eventId = $this->extractExprValue($this->getAttrArg($args, 'eventId', 0), $useMap, $namespace, $currentClass);
        $name    = $this->extractExprValue($this->getAttrArg($args, 'name', 1), $useMap, $namespace, $currentClass);

        if (! is_string($eventId) || $eventId === '' || ! is_string($name) || $name === '') {
            return null;
        }

        $handlerRaw = $this->extractExprValue($this->getAttrArg($args, 'handler', 2), $useMap, $namespace, $currentClass);

        $handler = $handlerRaw instanceof HandlerData
            ? $handlerRaw
            : $this->resolveListenerHandler($useMap, $namespace, $currentClass, $class, $method);

        return new ListenerData(eventId: $eventId, name: $name, handler: $handler);
    }

    /**
     * Resolve the handler from a #[ListenerHandler] sibling attribute, the method name,
     * or the class default ('handle').
     *
     * @param array<string, string> $useMap
     */
    protected function resolveListenerHandler(
        array $useMap,
        string $namespace,
        string $currentClass,
        Class_ $class,
        ClassMethod|null $method,
    ): HandlerData {
        $node = $method ?? $class;

        foreach ($this->findAttributesOnNode($node, ListenerHandler::class, $useMap, $namespace) as $attr) {
            $handlerRaw = $this->extractExprValue($this->getAttrArg($attr->args, 'handler', 0), $useMap, $namespace, $currentClass);

            if ($handlerRaw instanceof HandlerData) {
                return $handlerRaw;
            }
        }

        if ($method !== null) {
            return new HandlerData(class: $currentClass, method: $method->name->toString());
        }

        return new HandlerData(class: $currentClass, method: self::DEFAULT_HANDLE_METHOD);
    }

    /**
     * Convert a ListenerData into a PHP-Parser New_ expression for Valkyrja\Event\Data\Listener.
     */
    protected function buildListenerExpr(ListenerData $data): Expr
    {
        $args = [
            $this->buildNamedArg('eventId', $this->buildClassConstExpr($data->eventId)),
            $this->buildNamedArg('name', $this->buildStringExpr($data->name)),
        ];

        if ($data->handler !== null) {
            $args[] = $this->buildNamedArg('handler', $this->buildHandlerExpr($data->handler));
        } else {
            $args[] = $this->buildNamedArg('handler', new ConstFetch(new Name('null')));
        }

        return $this->buildNewExpr(ListenerModel::class, $args);
    }
}
