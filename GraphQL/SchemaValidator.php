<?php /** @noinspection PhpPrivateFieldCanBeLocalVariableInspection */


namespace Ozznest\GraphQLBundle\GraphQL;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Youshido\GraphQL\Exception\ConfigurationException;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Validator\SchemaValidator\SchemaValidator as BaseSchemaValidator;

class SchemaValidator extends BaseSchemaValidator
{
    private ContainerInterface $container;

    private $configValidator = null;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function validate(AbstractSchema $schema)
    {
        if (!$schema->getQueryType()->hasFields()) {
            throw new ConfigurationException('Schema has to have fields');
        }

        $this->configValidator = ConfigValidator::getInstance();


        foreach ($schema->getQueryType()->getConfig()->getFields() as $field) {

            $this->configValidator->assertValidConfig($this->_getType($field)->getConfig());

            if ($this->_getType($field)->getType() instanceof AbstractObjectType) {
                $this->assertInterfaceImplementationCorrect($this->_getType($field)->getType());
            }
        }
    }

    private function _getType($targetField){
        $fieldType = $targetField->getType();
        if(is_string($fieldType)){
            return  $this->container->get(str_replace('@','',$fieldType));
        }
        return $targetField;
    }

}