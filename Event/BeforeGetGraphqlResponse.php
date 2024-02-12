<?php

namespace Ozznest\GraphQLBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class BeforeGetGraphqlResponse extends Event
{

    private $graphqlQuery;

    private $graphqlVariables;

    private $graphqlResponse;


    public function __construct($graphqlQuery, $graphqlVariables, &$graphqlResponse)
    {
        $this->graphqlQuery = $graphqlQuery;
        $this->graphqlVariables = $graphqlVariables;
        $this->graphqlResponse = &$graphqlResponse;
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

    /**
     * @param mixed $graphqlResponse
     */
    public function setGraphqlResponse($graphqlResponse): void
    {
        $this->graphqlResponse = $graphqlResponse;
    }

}