<?php
/**
 * Created by PhpStorm.
 * User: o.chuiko
 * Date: 21.01.2019
 * Time: 12:38
 */

namespace Ozznest\GraphQLBundle\Event;


use Symfony\Contracts\EventDispatcher\Event;
use Youshido\GraphQL\Schema\AbstractSchema;

class GraphqlSchemaCreateEvent extends Event
{
    private AbstractSchema $schema;
    public function __construct(AbstractSchema $schema)
    {
        $this->schema = $schema;
    }

    public function getSchema(): AbstractSchema
    {
        return $this->schema;
    }

    public function setSchema(AbstractSchema $schema): void
    {
        $this->schema = $schema;
    }

}