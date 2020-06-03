<?php


namespace Fozzy\GraphQLBundle\GraphQL\Query;

use Youshido\GraphQL\Introspection\Field\SchemaField as BaseSchemaField;
use Fozzy\GraphQLBundle\GraphQL\Type\SchemaType;

class SchemaField extends BaseSchemaField
{

    public function getType()
    {
        return '@'.SchemaType::class;
    }
}