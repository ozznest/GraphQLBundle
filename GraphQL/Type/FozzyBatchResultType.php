<?php


namespace Fozzy\GraphQLBundle\GraphQL\Type;


use AppBundle\GraphQL\Type\Batch\BatchResultType;
use Fozzy\GraphQLBundle\GraphQL\FozzyListType;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\IntType;

class FozzyBatchResultType extends BatchResultType
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
           'items'  => new FozzyListType($listType), //@codingStandardsIgnoreLine
       ]);
    }

}