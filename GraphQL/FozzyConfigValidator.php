<?php


namespace Ozznest\GraphQLBundle\GraphQL;

use Youshido\GraphQL\Validator\ConfigValidator\ConfigValidator;


class FozzyConfigValidator extends ConfigValidator
{

    private function __construct()
    {
        $this->initializeRules();
    }

    protected function initializeRules()
    {
        $this->validationRules['type'] = new FozzyTypeValidationRule($this);
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