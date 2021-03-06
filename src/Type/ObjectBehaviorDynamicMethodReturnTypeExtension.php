<?php

declare(strict_types=1);

namespace Proget\PHPStan\PhpSpec\Type;

use PhpParser\Node\Expr\MethodCall;
use PhpSpec\Locator\PSR0\PSR0Locator;
use PhpSpec\Locator\Resource;
use PhpSpec\ObjectBehavior;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ResourceType;
use PHPStan\Type\Type;
use Proget\PHPStan\PhpSpec\Reflection\ObjectBehaviorMethodReflection;

final class ObjectBehaviorDynamicMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return ObjectBehavior::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection instanceof ObjectBehaviorMethodReflection;
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
    {
        if (!$methodReflection instanceof ObjectBehaviorMethodReflection) {
            throw new ShouldNotHappenException();
        }

        $returnType = $methodReflection->wrappedReflection()->getVariants()[0]->getReturnType();

        if ($returnType instanceof ArrayType) {
            $itemType = $returnType->getItemType();
            // nasty hack for PhpStan bug with wrong Resource class identification
            if ($itemType instanceof ResourceType && $methodReflection->getDeclaringClass()->getName() === PSR0Locator::class) {
                $itemType = new ObjectType(Resource::class);
            }

            return new SubjectArrayType($returnType->getKeyType(), $itemType);
        }

        return new SubjectType($returnType);
    }
}
