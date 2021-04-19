<?php


namespace Ozznest\GraphQLBundle\GraphQL;

use Youshido\GraphQL\Introspection\Field\SchemaField as BaseSchemaField;

class SchemaField extends BaseSchemaField
{

    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

}