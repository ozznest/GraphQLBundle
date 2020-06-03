<?php
/**
 * Created by PhpStorm.
 * User: o.chuiko
 * Date: 19.02.2019
 * Time: 17:30
 */

namespace Fozzy\GraphQLBundle\EventSubscriber;
use Fozzy\GraphQLBundle\Event\GraphqlSchemaCreateEvent;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class GraphqlSchemaCreate  implements EventSubscriberInterface
{
    private $mutations_dir;
    private $query_dir;
    /**
     * @var ContainerInterface
     */
    private $container;

    private $tagged_queries;

    private $tagged_mutations;

    public function __construct(ContainerInterface $container, $mutations_dir, $query_dir, $tagged_queries, $tagged_mutations)
    {
        $this->mutations_dir = $mutations_dir;
        $this->query_dir = $query_dir;
        $this->container = $container;
        $this->tagged_queries = $tagged_queries;
        $this->tagged_mutations = $tagged_mutations;
    }

    public static function getSubscribedEvents()
    {
        return [
            GraphqlSchemaCreateEvent::NAME => 'onSchemaCreate'
        ];
    }
    public function onSchemaCreate(GraphqlSchemaCreateEvent $event){
        $schema = $event->getSchema();
        $q = require  $this->query_dir;
        $m = require $this->mutations_dir;
        $schema->getQueryType()->addFields($q);
        $schema->getMutationType()->addFields($m);
    }
}