<?php


namespace Ozznest\GraphQLBundle\GraphQL;

use Youshido\GraphQL\Validator\ConfigValidator\ConfigValidator as BaseConfigValidator;


class ConfigValidator extends BaseConfigValidator
{

    private function __construct()
    {
        $this->initializeRules();
    }

    protected function initializeRules()
    {
        $this->validationRules['type'] = new TypeValidationRule($this);
    }

    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }

        self::$instance->clearErrors();

        return self::$instance;
    }
}