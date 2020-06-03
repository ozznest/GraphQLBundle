<?php


namespace Fozzy\GraphQLBundle;

use Fozzy\GraphQLBundle\DependencyInjection\Compiler\GraphQLCompilerPath;
use Fozzy\GraphQLBundle\GraphQL\GraphqlMutationInterface;
use Fozzy\GraphQLBundle\GraphQL\GraphqlQueryInterface;
use Fozzy\GraphQLBundle\GraphQL\GraphqlTypeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class FozzyGraphQLBundle extends Bundle
{
    public function build(ContainerBuilder $container){

        $container->registerForAutoconfiguration(GraphqlQueryInterface::class)
            ->addTag('graphql_query')
        ;

        $container->registerForAutoconfiguration(GraphqlMutationInterface::class)
            ->addTag('graphql_mutation')
        ;

        /*$container->registerForAutoconfiguration(GraphqlTypeInterface::class)
            ->addTag('graphql_type')
        ;*/

        parent::build($container);
        $container
            ->addCompilerPass(new GraphQLCompilerPath())
        ;
    }
}