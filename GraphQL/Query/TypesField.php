<?php


namespace Ozznest\GraphQLBundle\GraphQL\Query;

use Ozznest\GraphQLBundle\GraphQL\GraphqlQueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Youshido\GraphQL\Introspection\Field\TypesField as BaseTypesField;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Type\Union\AbstractUnionType;

class TypesField extends BaseTypesField implements GraphqlQueryInterface
{
    private $container;
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct([]);
    }


    protected function collectTypes(AbstractType $type)
    {
        if (is_object($type) && array_key_exists($type->getName(), $this->types)) return;

        switch ($type->getKind()) {
            case TypeMap::KIND_INTERFACE:
            case TypeMap::KIND_UNION:
            case TypeMap::KIND_ENUM:
            case TypeMap::KIND_SCALAR:
                $this->insertType($type->getName(), $type);

                if ($type->getKind() == TypeMap::KIND_UNION) {
                    /** @var AbstractUnionType $type */
                    foreach ($type->getTypes() as $subType) {
                        $this->collectTypes($this->_getType($subType));
                    }
                }

                break;

            case TypeMap::KIND_INPUT_OBJECT:
            case TypeMap::KIND_OBJECT:
                /** @var AbstractObjectType $namedType */
                $namedType = $this->_getType($type)->getNamedType();
                $this->checkAndInsertInterfaces($namedType);

                if ($this->insertType($namedType->getName(), $namedType)) {
                    $this->collectFieldsArgsTypes($namedType);
                }

                break;

            case TypeMap::KIND_LIST:
                    $this->collectTypes($this->_getType($type->getNamedType()));
                break;

            case TypeMap::KIND_NON_NULL:
                $this->collectTypes($type->getNamedType());

                break;
        }
    }



    private function insertType($name, $type)
    {
        if (!array_key_exists($name, $this->types)) {
            $this->types[$name] = $type;

            return true;
        }

        return false;
    }

    private function checkAndInsertInterfaces($type)
    {
        foreach ((array)$type->getConfig()->getInterfaces() as $interface) {
            $this->insertType($interface->getName(), $interface);

            if ($interface instanceof AbstractInterfaceType) {
                foreach ($interface->getImplementations() as $implementation) {
                    $this->insertType($implementation->getName(), $implementation);
                }
            }
        }
    }

    private function collectFieldsArgsTypes($type)
    {
        foreach ($type->getConfig()->getFields() as $field) {
            $arguments = $field->getConfig()->getArguments();

            if (is_array($arguments)) {
                foreach ($arguments as $argument) {
                    $this->collectTypes($argument->getType());
                }
            }

            $this->collectTypes($this->_getType($field)->getType());
        }
    }

    private function _getType($targetField){
        if(is_string($targetField)){
            return  $this->container->get(str_replace('@','',$targetField));
        }


        $fieldType = $targetField->getType();
        if(is_string($fieldType)){
            return  $this->container->get(str_replace('@','',$fieldType));
        }
        return $targetField;
    }

}