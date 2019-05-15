<?php

declare(strict_types=1);

namespace Proget\PHPStan\PhpSpec\NodeVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PHPStan\ShouldNotHappenException;
use Proget\PHPStan\PhpSpec\Registry\SpoofedCollaboratorRegistry;
use Proget\PHPStan\PhpSpec\Wrapper\SpoofedCollaborator;

final class CollaboratorResolver implements NodeVisitor
{
    /**
     * @var bool
     */
    private $isInExampleMethod = false;

    /**
     * @var string[]
     */
    private $spoofedCollaborators = [];

    /**
     * @var string
     */
    private $tempDir;

    public function __construct()
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('phpstan', true) . DIRECTORY_SEPARATOR;
        if(!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    public function __destruct()
    {
        if(is_dir($this->tempDir)) {
            foreach (glob($this->tempDir.'*.php') as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
    }

    public function beforeTraverse(array $nodes)
    {
        return null;
    }

    public function enterNode(Node $node)
    {
        if ($this->isExampleMethod($node)) {
            $this->isInExampleMethod = true;
        }
    }

    public function leaveNode(Node $node)
    {
        if ($this->isInExampleMethod && $node instanceof Node\Param && $node->type instanceof Node\Name\FullyQualified) {
            $reflection = new \ReflectionClass($node->type->toCodeString());
            if($reflection->isFinal()) {
                throw new ShouldNotHappenException(sprintf('Class %s can\'t be final.', $reflection->getName()));
            }
            $spoofedCollaborator = str_replace('\\', '', $node->type->toCodeString()).'Collaborator';
            if (!in_array($spoofedCollaborator, $this->spoofedCollaborators)) {
                $tempPath = $this->tempDir . uniqid('collaborator') . '.php';

                file_put_contents($tempPath, $this->getClassDefinition($reflection));
                require_once $tempPath;
                //class_alias($className = eval('return get_class('..');'), $spoofedCollaborator);
                //SpoofedCollaboratorRegistry::setAlias($className, $spoofedCollaborator);
                $this->spoofedCollaborators[] = $spoofedCollaborator;
            }

            return new Node\Param(
                $node->var,
                $node->default,
                new Node\Name\FullyQualified($spoofedCollaborator),
                $node->byRef,
                $node->variadic,
                $node->getAttributes()
            );
        }

        if ($this->isExampleMethod($node)) {
            $this->isInExampleMethod = false;
        }

        return null;
    }

    public function afterTraverse(array $nodes)
    {
        return null;
    }

    private function isExampleMethod(Node $node): bool
    {
        return $node instanceof Node\Stmt\ClassMethod && (preg_match('/^(it|its)[^a-zA-Z]/', $node->name->name) !== false || $node->name->name === 'let') ;
    }

    private function getClassDefinition(\ReflectionClass $reflection): string
    {
        if(!$reflection->isInterface()) {
            return '<?php abstract class '.str_replace('\\', '', $reflection->getName()).'Collaborator extends '.$reflection->getName().' implements ' . SpoofedCollaborator::class . ' {}';
        }

        $definition = '<?php class '.str_replace('\\', '', $reflection->getName()).'Collaborator implements ' . SpoofedCollaborator::class . ', '.$reflection->getName().' {' . PHP_EOL;

        foreach ($reflection->getMethods() as $method) {
            $definition .= $this->getMethodDefinition($method);
        }

        return $definition . PHP_EOL . '}';
    }

    private function getMethodDefinition(\ReflectionMethod $method): string
    {
        $definition = 'public function '.$method->getName() . '(';
        $definition .= implode(', ', $this->getParametersDefinition($method->getParameters()));
        $definition .= ')';
        $returnType = $method->getReturnType();
        $definition .= $returnType === null ? '' : ': '. ($returnType->allowsNull() ? '?' : '') . $returnType->getName();

        return $definition . ' {} ' . PHP_EOL;
    }

    /**
     * @param \ReflectionParameter[] $parameters
     */
    private function getParametersDefinition(array $parameters): array
    {
        return array_map(function(\ReflectionParameter $parameter): string {
            $definition = $parameter->allowsNull() && $parameter->getType() !== null ? '?' : '';
            $definition .= $parameter->getType() === null ? '' : $parameter->getType()->getName();
            $definition .= ' $'.$parameter->getName();
            if($parameter->isDefaultValueAvailable()) {
                $definition .= ' = ' . $this->getDefaultValueAsString($parameter->getDefaultValue());
            }

            return $definition;
        }, $parameters);
    }

    private function getDefaultValueAsString($value): string
    {
        if(is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if(is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }
}
