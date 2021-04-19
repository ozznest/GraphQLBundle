<?php
/**
 * Created by PhpStorm.
 * User: o.chuiko
 * Date: 21.01.2019
 * Time: 12:38
 */

namespace Ozznest\GraphQLBundle\Event;


use Symfony\Component\EventDispatcher\Event;
use Youshido\GraphQL\Schema\AbstractSchema;

class GraphqlSchemaCreateEvent extends Event
{
    const NAME = 'graphql_schema.create';

    private $schema;
    public function __construct(AbstractSchema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * @return AbstractSchema
     */
    public function getSchema(): AbstractSchema
    {
        return $this->schema;
    }

    /**
     * @param AbstractSchema $schema
     */
    public function setSchema(AbstractSchema $schema): void
    {
        $this->schema = $schema;
    }

}