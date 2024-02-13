<?php


namespace Ozznest\GraphQLBundle\GraphQL;

use Ozznest\GraphQLBundle\Utils\Helper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Youshido\GraphQL\Exception\ResolveException;
use Youshido\GraphQL\Execution\Context\ExecutionContextInterface;
use Youshido\GraphQL\Execution\DeferredResolverInterface;
use Youshido\GraphQL\Execution\DeferredResult;
use Youshido\GraphQL\Execution\Request;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Field\FieldInterface;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\InputList as AstInputList;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\InputObject as AstInputObject;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\Literal as AstLiteral;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\VariableReference;
use Youshido\GraphQL\Parser\Ast\FragmentReference;
use Youshido\GraphQL\Parser\Ast\Interfaces\FieldInterface as AstFieldInterface;
use Youshido\GraphQL\Parser\Ast\Query as AstQuery;
use Youshido\GraphQL\Parser\Ast\TypedFragmentReference;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\Enum\AbstractEnumType;
use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;
use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;
use Youshido\GraphQL\Type\ListType\AbstractListType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\AbstractScalarType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Type\Union\AbstractUnionType;
use Youshido\GraphQLBundle\Execution\Processor as BaseProcessor;

class Processor extends BaseProcessor
{

    public function __construct(ExecutionContextInterface $executionContext, EventDispatcherInterface $eventDispatcher)
    {

        parent::__construct($executionContext, $eventDispatcher);
        $this->resolveValidator = new ResolveValidator($this->executionContext);
    }


    /**
     * Apply post-process callbacks to all deferred resolvers.
     */
    protected function deferredResolve($resolvedValue, FieldInterface $field, callable $callback) {
        if ($resolvedValue instanceof DeferredResolverInterface) {
            $deferredResult = new DeferredResult($resolvedValue, function ($resolvedValue) use ($field, $callback) {
                // Allow nested deferred resolvers.
                return $this->deferredResolve($resolvedValue, $field, $callback);
            });

            // Whenever we stumble upon a deferred resolver, add it to the queue
            // to be resolved later.

            $type = $field->getType();


            $type = $type->getNamedType();
            if ($type instanceof AbstractScalarType || $type instanceof AbstractEnumType) {
                $this->deferredResultsLeaf[] = $deferredResult;
            } else {
                $this->deferredResultsComplex[] = $deferredResult;
            }

            return $deferredResult;
        }
        // For simple values, invoke the callback immediately.
        return $callback($resolvedValue);
    }

    private function _getType($targetField){
        if(is_string($targetField)){
            $service = str_replace('@','',$targetField);
            if($this->executionContext->getContainer()->has($service)){
                return  $this->executionContext->get($service);
            }
        }
        $fieldType = $targetField->getType();
        if(is_string($fieldType)){
            $service = str_replace('@','',$fieldType);
            if($this->executionContext->getContainer()->has($service)){
                return  $this->executionContext->get($service);
            }
        }
        return $targetField;
    }

    protected function resolveField(FieldInterface $field, AstFieldInterface $ast, $parentValue = null, $fromObject = false)
    {
        try {
            /** @var AbstractObjectType $type */
            $type        = $this->_getType($field)->getType();
            $nonNullType = $type->getNullableType();

            if (self::TYPE_NAME_QUERY == $ast->getName()) {
                return $nonNullType->getName();
            }

            $this->resolveValidator->assetTypeHasField($nonNullType, $ast);

            $targetField = $this->executionContext->getField($nonNullType, $ast->getName());

            $this->prepareAstArguments($targetField, $ast, $this->executionContext->getRequest());
            $this->resolveValidator->assertValidArguments($targetField, $ast, $this->executionContext->getRequest());


            $fieldType = $this->_getType($targetField)->getType();


            switch ($kind = $fieldType->getNullableType()->getKind()) {
                case TypeMap::KIND_ENUM:
                case TypeMap::KIND_SCALAR:
                    if ($ast instanceof AstQuery && $ast->hasFields()) {
                        throw new ResolveException(sprintf('You can\'t specify fields for scalar type "%s"', $targetField->getType()->getNullableType()->getName()), $ast->getLocation());
                    }

                    return $this->resolveScalar($targetField, $ast, $parentValue);

                case TypeMap::KIND_OBJECT:
                    /** @var $type AbstractObjectType */
                    if (!$ast instanceof AstQuery) {
                        throw new ResolveException(sprintf('You have to specify fields for "%s"', $ast->getName()), $ast->getLocation());
                    }

                    return $this->resolveObject($targetField, $ast, $parentValue);

                case TypeMap::KIND_LIST:
                    return $this->resolveList($targetField, $ast, $parentValue);

                case TypeMap::KIND_UNION:
                case TypeMap::KIND_INTERFACE:
                    if (!$ast instanceof AstQuery) {
                        throw new ResolveException(sprintf('You have to specify fields for "%s"', $ast->getName()), $ast->getLocation());
                    }

                    return $this->resolveComposite($targetField, $ast, $parentValue);

                default:
                    throw new ResolveException(sprintf('Resolving type with kind "%s" not supported', $kind));
            }
        } catch (\Exception $e) {
            $this->executionContext->addError($e);

            if ($fromObject) {
                throw $e;
            }

            return null;
        }
    }

    private function prepareArgumentValue($argumentValue, AbstractType $argumentType, Request $request)
    {
        switch ($argumentType->getKind()) {
            case TypeMap::KIND_LIST:
                /** @var $argumentType AbstractListType */
                $result = [];
                if ($argumentValue instanceof AstInputList || is_array($argumentValue)) {
                    $list = is_array($argumentValue) ? $argumentValue : $argumentValue->getValue();
                    foreach ($list as $item) {
                        $result[] = $this->prepareArgumentValue($item, $argumentType->getItemType()->getNullableType(), $request);
                    }
                } else {
                    if ($argumentValue instanceof VariableReference) {
                        return $this->getVariableReferenceArgumentValue($argumentValue, $argumentType, $request);
                    }
                }

                return $result;

            case TypeMap::KIND_INPUT_OBJECT:
                /** @var $argumentType AbstractInputObjectType */
                $result = [];
                if ($argumentValue instanceof AstInputObject) {
                    foreach ($argumentType->getFields() as $field) {
                        /** @var $field Field */
                        if ($field->getConfig()->has('defaultValue')) {
                            $result[$field->getName()] = $field->getType()->getNullableType()->parseInputValue($field->getConfig()->get('defaultValue'));
                        }
                    }
                    foreach ($argumentValue->getValue() as $key => $item) {
                        if ($argumentType->hasField($key)) {
                            $result[$key] = $this->prepareArgumentValue($item, $argumentType->getField($key)->getType()->getNullableType(), $request);
                        } else {
                            $result[$key] = $item;
                        }
                    }
                } else {
                    if ($argumentValue instanceof VariableReference) {
                        return $this->getVariableReferenceArgumentValue($argumentValue, $argumentType, $request);
                    } else {
                        if (is_array($argumentValue)) {
                            return $argumentValue;
                        }
                    }
                }

                return $result;

            case TypeMap::KIND_SCALAR:
            case TypeMap::KIND_ENUM:
                /** @var $argumentValue AstLiteral|VariableReference */
                if ($argumentValue instanceof VariableReference) {
                    return $this->getVariableReferenceArgumentValue($argumentValue, $argumentType, $request);
                } else {
                    if ($argumentValue instanceof AstLiteral) {
                        return $argumentValue->getValue();
                    } else {
                        return $argumentValue;
                    }
                }
        }

        throw new ResolveException('Argument type not supported');
    }

    private function prepareAstArguments(FieldInterface $field, AstFieldInterface $query, Request $request)
    {
        foreach ($query->getArguments() as $astArgument) {
            if ($field->hasArgument($astArgument->getName())) {
                $argumentType = $field->getArgument($astArgument->getName())->getType()->getNullableType();

                $astArgument->setValue($this->prepareArgumentValue($astArgument->getValue(), $argumentType, $request));
            }
        }
    }

    private function getVariableReferenceArgumentValue(VariableReference $variableReference, AbstractType $argumentType, Request $request)
    {
        $variable = $variableReference->getVariable();
        if ($argumentType->getKind() === TypeMap::KIND_LIST) {
            if (
                (!$variable->isArray() && !is_array($variable->getValue())) ||
                ($variable->getTypeName() !== $argumentType->getNamedType()->getNullableType()->getName()) ||
                ($argumentType->getNamedType()->getKind() === TypeMap::KIND_NON_NULL && $variable->isArrayElementNullable())
            ) {
                throw new ResolveException(sprintf('Invalid variable "%s" type, allowed type is "%s"', $variable->getName(), $argumentType->getNamedType()->getNullableType()->getName()), $variable->getLocation());
            }
        } else {
            if ($variable->getTypeName() !== $argumentType->getName()) {
                throw new ResolveException(sprintf('Invalid variable "%s" type, allowed type is "%s"', $variable->getName(), $argumentType->getName()), $variable->getLocation());
            }
        }

        $requestValue = $request->getVariable($variable->getName());
        if ((null === $requestValue && $variable->isNullable()) && !$request->hasVariable($variable->getName())) {
            throw new ResolveException(sprintf('Variable "%s" does not exist in request', $variable->getName()), $variable->getLocation());
        }

        return $requestValue;
    }

    protected function resolveObject(FieldInterface $field, AstFieldInterface $ast, $parentValue, $fromUnion = false)
    {
        $resolvedValue = $parentValue;
        if (!$fromUnion) {
            $resolvedValue = $this->doResolve($field, $ast, $parentValue);
        }

        return $this->deferredResolve($resolvedValue, $field, function ($resolvedValue) use ($field, $ast) {
            $this->resolveValidator->assertValidResolvedValueForField($field, $resolvedValue);

            if (null === $resolvedValue) {
                return null;
            }
            /** @var AbstractObjectType $type */
            $type = $this->_getType($field)->getType()->getNullableType();

            try {
                return $this->collectResult($field, $type, $ast, $resolvedValue);
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    /**
     * @param AstFieldInterface  $ast
     * @param                    $resolvedValue
     * @return array
     */
    private function collectResult(FieldInterface $field, AbstractObjectType $type, $ast, $resolvedValue)
    {
        $results = [];
        if(Helper::isService($resolvedValue)){
            $resolvedValue = $this->executionContext->get(Helper::getServiceString($resolvedValue));
        }
        foreach ($ast->getFields() as $astField) {
            switch (true) {
                case $astField instanceof TypedFragmentReference:
                    $astName  = $astField->getTypeName();
                    $typeName = $type->getName();

                    if ($typeName !== $astName) {
                        foreach ($type->getInterfaces() as $interface) {
                            if ($interface->getName() === $astName) {
                                $result = array_replace_recursive($result, $this->collectResult($field, $type, $astField, $resolvedValue));

                                break;
                            }
                        }

                        continue 2;
                    }

                    $result = array_replace_recursive($result, $this->collectResult($field, $type, $astField, $resolvedValue));

                    break;

                case $astField instanceof FragmentReference:
                    $astFragment      = $this->executionContext->getRequest()->getFragment($astField->getName());
                    $astFragmentModel = $astFragment->getModel();
                    $typeName         = $type->getName();

                    if ($typeName !== $astFragmentModel) {
                        foreach ($type->getInterfaces() as $interface) {
                            if ($interface->getName() === $astFragmentModel) {
                                $result = array_replace_recursive($result, $this->collectResult($field, $type, $astFragment, $resolvedValue));

                                break;
                            }
                        }

                        continue 2;
                    }

                    $result = array_replace_recursive($result, $this->collectResult($field, $type, $astFragment, $resolvedValue));

                    break;

                default:
                    $result = array_replace_recursive($result, [$this->getAlias($astField) => $this->resolveField($field, $astField, $resolvedValue, true)]);
            }
        }

        return $result;
    }

    private function getAlias(AstFieldInterface $ast)
    {
        return $ast->getAlias() ?: $ast->getName();
    }
    protected function resolveList(FieldInterface $field, AstFieldInterface $ast, $parentValue)
    {
        /** @var AstQuery $ast */
        $resolvedValue = $this->doResolve($field, $ast, $parentValue);

        if(Helper::isService($resolvedValue)){
            $resolvedValue = $this->executionContext->getContainer()->get(Helper::getServiceString($resolvedValue));
        }

        return $this->deferredResolve($resolvedValue, $field, function ($resolvedValue) use ($field, $ast) {


            $this->resolveValidator->assertValidResolvedValueForField($field, $resolvedValue);

            if (null === $resolvedValue) {
                return null;
            }

            /** @var AbstractListType $type */
            $type     = $field->getType()->getNullableType();
            $itemType = $this->_getType($type->getNamedType());

            $fakeAst = clone $ast;
            if ($fakeAst instanceof AstQuery) {
                $fakeAst->setArguments([]);
            }

            $fakeField = new Field([
                'name' => $field->getName(),
                'type' => $itemType,
                'args' => $field->getArguments(),
            ]);

            $result = [];
            foreach ($resolvedValue as $resolvedValueItem) {
                try {
                    $fakeField->getConfig()->set('resolve', function () use ($resolvedValueItem) {
                        return $resolvedValueItem;
                    });

                    switch ($itemType->getNullableType()->getKind()) {
                        case TypeMap::KIND_ENUM:
                        case TypeMap::KIND_SCALAR:
                            $value = $this->resolveScalar($fakeField, $fakeAst, $resolvedValueItem);

                            break;


                        case TypeMap::KIND_OBJECT:
                            $value = $this->resolveObject($fakeField, $fakeAst, $resolvedValueItem);

                            break;

                        case TypeMap::KIND_UNION:
                        case TypeMap::KIND_INTERFACE:
                            $value = $this->resolveComposite($fakeField, $fakeAst, $resolvedValueItem);

                            break;

                        default:
                            $value = null;
                    }
                } catch (\Exception $e) {
                    $this->executionContext->addError($e);

                    $value = null;
                }

                $result[] = $value;
            }

            return $result;
        });
    }

    protected function resolveComposite(FieldInterface $field, AstFieldInterface $ast, $parentValue)
    {
        /** @var AstQuery $ast */
        $resolvedValue = $this->doResolve($field, $ast, $parentValue);
        return $this->deferredResolve($resolvedValue, $field, function ($resolvedValue) use ($field, $ast) {
            $this->resolveValidator->assertValidResolvedValueForField($field, $resolvedValue);

            if (null === $resolvedValue) {
                return null;
            }

            /** @var AbstractUnionType $type */
            $type         = $this->_getType($field->getType())->getNullableType();
            $resolveInfo = new ResolveInfo(
                $field,
                $ast instanceof AstQuery ? $ast->getFields() : [],
                $this->executionContext
            );
            $resolvedType = $this->_getType($type->resolveType($resolvedValue, $resolveInfo));

            if (!$resolvedType) {
                throw new ResolveException('Resolving function must return type');
            }

            if ($type instanceof AbstractInterfaceType) {
                $this->resolveValidator->assertTypeImplementsInterface($resolvedType, $type);
            } else {
                $this->resolveValidator->assertTypeInUnionTypes($resolvedType, $type);
            }

            $fakeField = new Field([
                'name' => $field->getName(),
                'type' => $resolvedType,
                'args' => $field->getArguments(),
            ]);

            return $this->resolveObject($fakeField, $ast, $resolvedValue, true);
        });
    }

}