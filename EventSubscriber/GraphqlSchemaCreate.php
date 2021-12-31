<?php
/**
 * Created by PhpStorm.
 * User: o.chuiko
 * Date: 19.02.2019
 * Time: 17:30
 */

namespace Ozznest\GraphQLBundle\EventSubscriber;
use Ozznest\GraphQLBundle\Event\GraphqlSchemaCreateEvent;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class GraphqlSchemaCreate  implements EventSubscriberInterface
{
    private ?string $mutations_dir;
    private ?string $query_dir;

    private ContainerInterface $container;

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
            GraphqlSchemaCreateEvent::class => 'onSchemaCreate'
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