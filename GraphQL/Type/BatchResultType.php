<?php


namespace Ozznest\GraphQLBundle\GraphQL\Type;


use AppBundle\GraphQL\Type\Batch\BatchResultType as BaseBatchResultType;
use Ozznest\GraphQLBundle\GraphQL\ListType;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\Scalar\IntType;

class BatchResultType extends BaseBatchResultType
{
    private $listItemType = null;

    public function __construct($listItemType)
    {
        parent::__construct([
            'name' => sprintf('%ssResult', $listItemType),
        ]);

        $this->listItemType = $listItemType;
    }
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