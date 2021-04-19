<?php


namespace Ozznest\GraphQLBundle\GraphQL\Query;


use Ozznest\GraphQLBundle\GraphQL\GraphqlQueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\InputField;
use Youshido\GraphQL\Introspection\QueryType;
use Youshido\GraphQL\Introspection\Traits\TypeCollectorTrait;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;
use Youshido\GraphQL\Field\AbstractField;

class FozzyTypeDefinitionField extends AbstractField implements GraphqlQueryInterface
{

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct([]);
    }

    //use TypeCollectorTrait;

    public function resolve($value = null, array $args, ResolveInfo $info)
    {
        $schema = $info->getExecutionContext()->getSchema();
        $this->collectTypes($schema->getQueryType());
        $this->collectTypes($schema->getMutationType());

        foreach ($schema->getTypesList()->getTypes() as $type) {
            $this->collectTypes($type);
        }

        foreach ($this->types as $name => $info) {
            if ($name == $args['name']) {
                return $info;
            }
        }

        return null;
    }

    public function build(FieldConfig $config)
    {
        $config->addArgument(
            new InputField(
                [
                    'name' => 'name',
                    'type' => new NonNullType(new StringType())
                ]
            )
        );
    }


    /**
     * @return String type name
     */
    public function getName()
    {
        return '__type';
    }

    /**
     * @return AbstractObjectType
     */
    public function getType()
    {
        return new QueryType();
    }
}