<?php


namespace Ozznest\GraphQLBundle\GraphQL;

use Youshido\GraphQL\Config\Object\ListTypeConfig;
use Youshido\GraphQL\Type\ListType\AbstractListType;
use Youshido\GraphQL\Type\TypeInterface;

class FozzyListType extends AbstractListType
{
    public function __construct($itemType)
    {
        $this->config = new ListTypeConfig(['itemType' => $itemType], $this, true);
    }

    public function getItemType()
    {
        return $this->getConfig()->get('itemType');
    }

    public function getName()
    {
        return null;
    }

    protected function validList($value, $returnValue = false)
    {
        $itemType = $this->config->get('itemType');


        if(is_string($itemType) && substr($itemType,0,1)== '@'){
            $class =  str_replace('@','',$itemType);
            $r = new \ReflectionClass($class);
            if($r->implementsInterface (TypeInterface::class)){
                return true;
            }
        }


        if ($value && $itemType->isInputType()) {
            foreach ($value as $item) {
                if (!$itemType->isValidValue($item)) {
                    return $returnValue ? $item : false;
                }
            }
        }

        return true;
    }

}