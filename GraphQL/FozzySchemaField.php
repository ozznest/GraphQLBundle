<?php


namespace Fozzy\GraphQLBundle\GraphQL;

use Youshido\GraphQL\Introspection\Field\SchemaField;

class FozzySchemaField extends SchemaField
{

    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

}