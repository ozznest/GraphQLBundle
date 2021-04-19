<?php


namespace Ozznest\GraphQLBundle\GraphQL;

use Fozzy\GraphQLBundle\Utils\Helper;
use Youshido\GraphQL\Exception\ResolveException;
use Youshido\GraphQL\Execution\Context\ExecutionContext;
use Youshido\GraphQL\Field\FieldInterface;
use Youshido\GraphQL\Parser\Ast\Interfaces\FieldInterface as AstFieldInterface;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Type\TypeService;
use Youshido\GraphQL\Type\Union\AbstractUnionType;
use Youshido\GraphQL\Validator\ResolveValidator\ResolveValidator;

class ResolveValidator extends ResolveValidator
{

    private $executionContext;

    public function __construct(ExecutionContext $executionContext)
    {
        $this->executionContext = $executionContext;
    }

    private function _getType($targetField){
        if(Helper::isService($targetField)){
            $service = Helper::getServiceString($targetField);
            if($this->executionContext->getContainer()->has($service)){
                return  $this->executionContext->get($service);
            }
        }
        return $targetField;
    }

    public function assertValidResolvedValueForField(FieldInterface $field, $resolvedValue)
    {

        $type = $this->_getType($field->getType());
        if (null === $resolvedValue && $type->getKind() === TypeMap::KIND_NON_NULL) {
            throw new ResolveException(sprintf('Cannot return null for non-nullable field "%s"', $field->getName()));
        }
        $nullableFieldType = $type->getNullableType();
        $resolvedValue = $this->_getType($resolvedValue);
        if(is_array($resolvedValue)){
            foreach ($resolvedValue as &$v){
                if(Helper::isService($v)){
                    $v = $this->executionContext->getContainer()->get(Helper::getServiceString($v));
                }
            }
        }
        if (!$nullableFieldType->isValidValue($resolvedValue)) {
            $error = $nullableFieldType->getValidationError($resolvedValue) ?: '(no details available)';
            throw new ResolveException(sprintf('Not valid resolved type for field "%s": %s', $field->getName(),
                                               $error));
        }
    }

    public function assetTypeHasField(AbstractType $objectType, AstFieldInterface $ast)
    {
        /** @var AbstractObjectType $objectType */
        if ($this->executionContext->getField($objectType, $ast->getName()) !== null) {
            return;
        }

        if (!(TypeService::isObjectType($objectType) || TypeService::isInputObjectType($objectType)) || !$objectType->hasField($ast->getName())) {
            $availableFieldNames = implode(', ', array_map(function (FieldInterface $field) {
                return sprintf('"%s"', $field->getName());
            }, $objectType->getFields()));
            throw new ResolveException(sprintf('Field "%s" not found in type "%s". Available fields are: %s', $ast->getName(), $objectType->getNamedType()->getName(), $availableFieldNames), $ast->getLocation());
        }
    }

    public function assertTypeInUnionTypes(AbstractType $type, AbstractUnionType $unionType)
    {
        foreach ($unionType->getTypes() as $unionTypeItem) {
            $unionTypeItem = $this->_getType($unionTypeItem);

            if ($unionTypeItem->getName() === $type->getName()) {
                return;
            }
        }

        throw new ResolveException(sprintf('Type "%s" not exist in types of "%s"', $type->getName(), $unionType->getName()));
    }
}