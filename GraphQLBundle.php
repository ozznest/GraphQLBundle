<?php


namespace Fozzy\GraphQLBundle;

use Fozzy\GraphQLBundle\DependencyInjection\Compiler\GraphQLCompilerPath;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GraphQLBundle extends Bundle
{
    public function build(ContainerBuilder $container){
        parent::build($container);
        $container
            ->addCompilerPass(new GraphQLCompilerPath(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 5)
        ;
    }
}