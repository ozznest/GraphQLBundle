<?php


namespace Ozznest\GraphQLBundle\GraphQL\Query;

use Youshido\GraphQL\Introspection\Field\SchemaField as BaseSchemaField;
use Ozznest\GraphQLBundle\GraphQL\Type\SchemaType;

class SchemaField extends BaseSchemaField
{

    public function getType()
    {
        return '@'.SchemaType::class;
    }
}