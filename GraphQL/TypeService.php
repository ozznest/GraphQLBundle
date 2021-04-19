<?php


namespace Ozznest\GraphQLBundle\GraphQL;


use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\TypeService as BaseTypeService;

class TypeService extends BaseTypeService
{
    public static function isGraphQLType($type)
    {
        return $type instanceof AbstractType || static::isScalarType($type) || substr($type,0,1)== '@';
    }
}