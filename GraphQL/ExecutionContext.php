<?php


namespace Ozznest\GraphQLBundle\GraphQL;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Youshido\GraphQL\Execution\Request;
use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Validator\ErrorContainer\ErrorContainerTrait;
use Youshido\GraphQL\Execution\Context\ExecutionContext as BaseExecutionContext;
use Youshido\GraphQLBundle\Execution\Container\SymfonyContainer;


class ExecutionContext extends BaseExecutionContext
{
    use ErrorContainerTrait;

    /** @var AbstractSchema */
    private $schema;

    //private $container;

    /** @var Request */
    private $request;

    /** @var array */
    private $typeFieldLookupTable;

    /**
     * ExecutionContext constructor.
     *
     * @param AbstractSchema $schema
     */
    public function __construct(AbstractSchema $schema, ContainerInterface $container)
    {
        $this->schema = $schema;
        $cnt = new SymfonyContainer();
        $cnt->setContainer($container);
        $this->setContainer($cnt);
        $validator = FozzyConfigValidator::getInstance();
        $validator->addRule('type', new FozzyTypeValidationRule($validator));
        $this->validateSchema();

        $this->typeFieldLookupTable = [];
    }

    protected function validateSchema()
    {
        try {
            (new FozzySchemaValidator($this->getContainer()->getSymfonyContainer()))->validate($this->schema);
        } catch (\Exception $e) {
            $this->addError($e);
        };
    }



    /**
     * @param AbstractObjectType $type
     * @param string             $fieldName
     *
     * @return Field
     */
    public function getField(AbstractObjectType $type, $fieldName)
    {
        $typeName = $type->getName();

        if (!array_key_exists($typeName, $this->typeFieldLookupTable)) {
            $this->typeFieldLookupTable[$typeName] = [];
        }

        if (!array_key_exists($fieldName, $this->typeFieldLookupTable[$typeName])) {
            $this->typeFieldLookupTable[$typeName][$fieldName] = $type->getField($fieldName);
        }

        return $this->typeFieldLookupTable[$typeName][$fieldName];
    }

    /**
     * @return AbstractSchema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @param AbstractSchema $schema
     *
     * @return \Youshido\GraphQL\Execution\Context\ExecutionContext
     */
    public function setSchema(AbstractSchema $schema)
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param Request $request
     *
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

}