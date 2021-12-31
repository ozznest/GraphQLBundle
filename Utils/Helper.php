<?php


namespace Ozznest\GraphQLBundle\Utils;


class Helper
{
    public static function isService($v):bool{
        return is_string($v) && \substr($v,0,1)== '@';
    }

    public static function getServiceString($v):string{
        return str_replace('@','', $v);
    }
}