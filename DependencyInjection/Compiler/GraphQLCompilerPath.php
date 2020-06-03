<?php

namespace Fozzy\GraphQLBundle\DependencyInjection\Compiler;

use Fozzy\GraphQLBundle\Annotation\GraphQL\MarkToRemove;

use Fozzy\GraphQLBundle\GraphQL\ExecutionContext;
use Fozzy\GraphQLBundle\GraphQL\GraphqlMutationInterface;
use Fozzy\GraphQLBundle\GraphQL\GraphqlQueryInterface;
use Fozzy\GraphQLBundle\GraphQL\GraphqlTypeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\ClassLoader\ClassMapGenerator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Filesystem\Filesystem;
use Youshido\GraphQL\Field\AbstractField;



class GraphQLCompilerPath implements CompilerPassInterface
{

    private $container;

    private $annotations_reader;

    private $queries;

    private $mutations;

    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->annotations_reader = $container->get('annotation_reader');
        $this->mutations = [];
        $this->queries = [];
        foreach ($container->getParameter('kernel.bundles') as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            $dir_mutations = dirname($reflection->getFileName()).'/GraphQL/Mutation';
            if(is_dir($dir_mutations)){
                $this->mutations = array_merge($this->mutations, $this->_getMutations($dir_mutations));
            }
            $dir_queries =  dirname($reflection->getFileName()).'/GraphQL/Query';
            if(is_dir($dir_queries)){
                $this->queries = array_merge($this->queries, $this->_getMutations($dir_queries));
            }
            $types = dirname($reflection->getFileName()).'/GraphQL/Type';
            if(is_dir($types)){
                $this->processTypes($types);
            }
        }

        $this->processTaggedQueries();
        $this->processTaggedMutations();

        $cache_dir = $container->getParameter('kernel.cache_dir');
        $mutations_dir = $cache_dir.'/graphql/';

        $mutations_file = $mutations_dir.'mutations.php';
        $queries_file = $mutations_dir.'queries.php';
        $filesystem = new Filesystem();

        if(!$filesystem->exists($mutations_dir)){
            $filesystem->mkdir($mutations_dir);
        }

        $filesystem->dumpFile($mutations_file, sprintf("<?php return \n [%s]\n;", implode(",\n", $this->mutations)));
        $filesystem->dumpFile($queries_file, sprintf("<?php return \n [%s]\n;", implode(",\n", $this->queries)));
        $container->setParameter('graphql.mutations_cache', $mutations_file);
        $container->setParameter('graphql.queries_cache', $queries_file);
        $this->container->setParameter('graphql.execution_context.class',ExecutionContext::class );

        $definition = $this->container->getDefinition('graphql.execution_context');
        $definition->setAutowired(true);
        $this->container->setDefinition('graphql.execution_context', $definition);
    }



    private function processTaggedQueries(){
        $callback = function ($res) {
            $this->queries = array_merge($this->queries, $res);
        };
        $this->processByTag('graphql_query', $callback);
    }

    private function processTaggedMutations(){
        $callback = function ($res) {
            $this->mutations = array_merge($this->mutations, $res);
        };
        $this->processByTag('graphql_mutation', $callback);
    }

    private function processByTag($tag, callable  $callback){
        $services = $this->container->findTaggedServiceIds($tag, true);
        $tagged_with_container = [];
        if($services && count($services)){
            foreach ($services as $id => $tags){
                $def = $this->container->getDefinition($id);
                $class_string = '$this->container->get('.$id.'::class)';
                $cl = new \ReflectionClass($id);
                if(!$cl->isSubclassOf(AbstractField::class)) continue;
                if(($ann = $this->annotations_reader->getClassAnnotation($cl, MarkToRemove::class))){
                    if($ann->force) {
                        $class_string = '//' . $class_string;
                    }
                    $class_string .= $this->getCommentByAnnotation($ann);
                }
                $tagged_with_container[] = $class_string;
                $def->setPublic(true);
            }
            $callback($tagged_with_container);
        }
    }

    private function processTypes($types){
        $types_map = ClassMapGenerator::createMap($types);
        foreach ($types_map as $class => $path) {
            $cl = new \ReflectionClass($class);
            if($cl->implementsInterface(GraphqlTypeInterface::class)){
                $this->addToContainer($class, 'graphql_type');
            }
        }
    }

    private function _getMutations($path)
    {

        $class_map = ClassMapGenerator::createMap($path);
        $mutations = [];
        foreach ($class_map as $class => $path) {
            $cl = new \ReflectionClass($class);

            if($cl->implementsInterface(GraphqlQueryInterface::class)){
                $this->validateConstructor($cl);
                $this->addToContainer($class,'graphql_query');
                continue;
            }elseif ($cl->implementsInterface(GraphqlMutationInterface::class)){
                $this->validateConstructor($cl);
                $this->addToContainer($class,'graphql_mutation');
                continue;
            }

            if($cl->isTrait() || $cl->isAbstract()) continue;
            $constructor = $cl->getConstructor();
            $arguments = $constructor->getParameters();
            $constructor_is_valid = (count($arguments) == 1 && $arguments[0]->isArray());

            if ($cl->isInstantiable() && $constructor_is_valid && $cl->isSubclassOf(AbstractField::class)) {
                $class_string = ' new ' . $class . '()';
                if(($ann = $this->annotations_reader->getClassAnnotation($cl, MarkToRemove::class))){
                    if($ann->force) {
                        $class_string = '//' . $class_string;
                    }
                    $class_string .= $this->getCommentByAnnotation($ann);
                }
                $mutations[] = $class_string;
            }
        }
        return $mutations ?? [];
    }

    private function validateConstructor(\ReflectionClass $class){
        $constructor = $class->getConstructor();
        $filename = $constructor->getFileName();
        $start_line = $constructor->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
        $end_line = $constructor->getEndLine();
        $length = $end_line - $start_line;

        $source = file($filename);
        $body = implode("", array_slice($source, $start_line, $length));
        $body = preg_replace('/\s+/', '', $body);
        if($constructor->getDeclaringClass() == $class){
            $type = $class->implementsInterface(GraphqlQueryInterface::class) ? 'Query' : 'Mutation';
            if((strpos($body, 'parent::__construct([])') === FALSE)) {
                throw new \RuntimeException($type .' ' . $class->getName() . ' must call parent::__construct([])');
            }elseif ((strpos($body, '//parent::__construct([])') !== FALSE) || (strpos($body, '/*parent::__construct([]);*/') !== FALSE)){
                throw new \RuntimeException($type .' ' . $class->getName() . ' must call parent::__construct([])');
            }
        }

    }

    private function addToContainer($class, $tag ){
        $definition = new Definition($class);
        $definition->setAutowired(true);
        $definition->setPrivate(false);
        $definition->addTag($tag);
        //$definition->setLazy(true);
        $this->container->setDefinition($class, $definition);
    }

    private function getCommentByAnnotation($ann){
        //$str = '/* mark to remove */';
        $el = ['mark to remove'];
        if(isset($ann->version)){
            //$str .= "/* version: {$ann->version} */";
            $el[] = "version: {$ann->version}";
        }
        if(isset($ann->from)){
            //$str .= "/* from: {$ann->from} */";
            $el[] = " from: {$ann->from} ";
        }
        return '/*'. implode(' ', $el) . '*/';
    }
}
