<?php

namespace Ozznest\GraphQLBundle\Event;

use Symfony\Component\EventDispatcher\Event;

final class BeforeGetGraphqlResponse extends Event
{
    const NAME = 'before_get_graphql_response';

    private $graphqlQuery;

    private $graphqlVariables;

    private $graphqlResponse;

    public function __construct($graphqlQuery, $graphqlVariables, $graphqlResponse)
    {
        $this->graphqlQuery = $graphqlQuery;
        $this->graphqlVariables = $graphqlVariables;
        $this->graphqlResponse = $graphqlResponse;
    }

    /**
     * @return string
     */
    public function getGraphqlQuery()
    {
        return $this->graphqlQuery;
    }

    /**
     * @return array
     */
    public function getGraphqlVariables()
    {
        return $this->graphqlVariables;
    }

    /**
     * @return array
     */
    public function getGraphqlResponse()
    {
        return $this->graphqlResponse;
    }
}