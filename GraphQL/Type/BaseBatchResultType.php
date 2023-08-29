<?php

namespace Ozznest\GraphQLBundle\GraphQL\Type;

use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Ozznest\GraphQLBundle\Utils\Helper;
use Ozznest\GraphQLBundle\GraphQL\ListType;
/**
 * Class BatchResultType
 */
class BaseBatchResultType extends AbstractObjectType
{
    /** @var null|AbstractType  */
    private $listItemType = null;


    /**
     * BatchResultType constructor.
     *
     * @param AbstractType|string $listItemType
     */
    public function __construct($listItemType)
    {

        if(Helper::isService($listItemType)){
            $name = Helper::getServiceString($listItemType);
            $arr = explode('\\', $name);
            $name = end($arr);
        }else{
            $name = $listItemType;
        }


        parent::__construct([
            'name' => sprintf('%ssResult', $name),
        ]);

        $this->listItemType = $listItemType;
    }

    /**
     * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
     */
    public function build($config)
    {
        $listType =  $this->listItemType;


        $config->addFields([
            'limit'  => new IntType(),
            'offset' => new IntType(),
            'count'  => new IntType(),
            'items'  => new ListType($listType), //@codingStandardsIgnoreLine
        ]);
    }
}