<?php
namespace Ozznest\GraphQLBundle\Annotation\GraphQL;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Annotation\Target({"CLASS"})
 */
class MarkToRemove
{
    public $force = false;

    public $version = null;

    public $from = null;
}