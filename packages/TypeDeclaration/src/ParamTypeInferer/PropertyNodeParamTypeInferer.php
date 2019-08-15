<?php declare(strict_types=1);

namespace Rector\TypeDeclaration\ParamTypeInferer;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Type\ArrayType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Node\Manipulator\PropertyFetchManipulator;
use Rector\TypeDeclaration\Contract\ParamTypeInfererInterface;
use Rector\TypeDeclaration\TypeInferer\AbstractTypeInferer;

final class PropertyNodeParamTypeInferer extends AbstractTypeInferer implements ParamTypeInfererInterface
{
    /**
     * @var PropertyFetchManipulator
     */
    private $propertyFetchManipulator;

    public function __construct(PropertyFetchManipulator $propertyFetchManipulator)
    {
        $this->propertyFetchManipulator = $propertyFetchManipulator;
    }

    /**
     * @return string[]
     */
    public function inferParam(Param $param): array
    {
        /** @var Class_|null $classNode */
        $classNode = $param->getAttribute(AttributeKey::CLASS_NODE);
        if ($classNode === null) {
            return [];
        }

        $paramName = $this->nameResolver->getName($param);

        /** @var Node\Stmt\ClassMethod $classMethod */
        $classMethod = $param->getAttribute(AttributeKey::PARENT_NODE);

        $propertyStaticTypes = [];

        $this->callableNodeTraverser->traverseNodesWithCallable($classMethod, function (Node $node) use (
            $paramName,
            &$propertyStaticTypes
        ) {
            if (! $this->propertyFetchManipulator->isVariableAssignToThisPropertyFetch($node, $paramName)) {
                return null;
            }

            /** @var Assign $node */
            $staticType = $this->nodeTypeResolver->getNodeStaticType($node->var);

            /** @var Type|null $staticType */
            if ($staticType) {
                $propertyStaticTypes[] = $staticType;
            }

            return null;
        });

        $propertyStaticTypes = array_unique($propertyStaticTypes);

        if ($propertyStaticTypes === []) {
            return [];
        }

        if ($propertyStaticTypes[0] instanceof ArrayType) {
            $itemType = $propertyStaticTypes[0]->getItemType();
            if ($itemType instanceof IntegerType) {
                return ['int'];
            }
        }

        return [];
    }
}
