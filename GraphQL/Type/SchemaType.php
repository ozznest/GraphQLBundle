<?php


namespace Ozznest\GraphQLBundle\GraphQL\Type;

use Ozznest\GraphQLBundle\GraphQL\GraphqlTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Introspection\DirectiveType;
use Ozznest\GraphQLBundle\GraphQL\Query\TypesField;
use Youshido\GraphQL\Introspection\QueryType;
use Youshido\GraphQL\Introspection\SchemaType as BaseSchemaType;
use Youshido\GraphQL\Type\ListType\ListType;

class SchemaType extends BaseSchemaType implements GraphqlTypeInterface
{

    private $container;
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct([]);
    }


    public function build($config)
    {
        $config
            ->addField(new Field([
                                     'name'    => 'queryType',
                                     'type'    => new QueryType(),
                                     'resolve' => [$this, 'resolveQueryType']
                                 ]))
            ->addField(new Field([
                                     'name'    => 'mutationType',
                                     'type'    => new QueryType(),
                                     'resolve' => [$this, 'resolveMutationType']
                                 ]))
            ->addField(new Field([
                                     'name'    => 'subscriptionType',
                                     'type'    => new QueryType(),
                                     'resolve' => [$this, 'resolveSubscriptionType']
                                 ]))
            ->addField($this->container->get(TypesField::class))
            ->addField(new Field([
                                     'name'    => 'directives',
                                     'type'    => new ListType(new DirectiveType()),
                                     'resolve' => [$this, 'resolveDirectives']
                                 ]));
    }

}