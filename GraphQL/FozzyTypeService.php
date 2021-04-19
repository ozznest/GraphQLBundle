<?php


namespace Ozznest\GraphQLBundle\GraphQL;


use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\TypeService;

class FozzyTypeService extends TypeService
{
    public static function isGraphQLType($type)
    {
        return $type instanceof AbstractType || static::isScalarType($type) || substr($type,0,1)== '@';
    }
}