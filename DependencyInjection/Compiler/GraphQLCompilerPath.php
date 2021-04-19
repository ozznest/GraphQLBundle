<?php

namespace Ozznest\GraphQLBundle\DependencyInjection\Compiler;

use Composer\Autoload\ClassMapGenerator;
use Ozznest\GraphQLBundle\Annotation\GraphQL\MarkToRemove;
use Ozznest\GraphQLBundle\GraphQL\ExecutionContext;
use Ozznest\GraphQLBundle\GraphQL\GraphqlMutationInterface;
use Ozznest\GraphQLBundle\GraphQL\GraphqlQueryInterface;
use Ozznest\GraphQLBundle\GraphQL\GraphqlTypeInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Filesystem\Filesystem;
use Youshido\GraphQL\Field\AbstractField;

/**
 * Class GraphQLCompilerPath
 *
 * @package Ozznest\GraphQLBundle\DependencyInjection\Compiler
 */
class GraphQLCompilerPath implements CompilerPassInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    private $annotations_reader;

    private $queries;

    private $mutations;

    /**
     * @param ContainerBuilder $container
     *
     * @throws ReflectionException
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->annotations_reader = $container->get('annotation_reader');
        $this->mutations = [];
        $this->queries = [];
        foreach ($container->getParameter('kernel.bundles') as $bundle) {
            $reflection = new ReflectionClass($bundle);
            $dir_mutations = dirname($reflection->getFileName()).'/GraphQL/Mutation';
            if (is_dir($dir_mutations)) {
                $this->mutations = array_merge($this->mutations, $this->_getMutations($dir_mutations));
            }
            $dir_queries = dirname($reflection->getFileName()).'/GraphQL/Query';
            if (is_dir($dir_queries)) {
                $this->queries = array_merge($this->queries, $this->_getMutations($dir_queries));
            }
            $types = dirname($reflection->getFileName()).'/GraphQL/Type';
            if (is_dir($types)) {
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

        if (!$filesystem->exists($mutations_dir)) {
            $filesystem->mkdir($mutations_dir);
        }

        $filesystem->dumpFile($mutations_file, sprintf("<?php return \n [%s]\n;", implode(",\n", $this->mutations)));
        $filesystem->dumpFile($queries_file, sprintf("<?php return \n [%s]\n;", implode(",\n", $this->queries)));
        $container->setParameter('graphql.mutations_cache', $mutations_file);
        $container->setParameter('graphql.queries_cache', $queries_file);
        $this->container->setParameter('graphql.execution_context.class', ExecutionContext::class);

        $definition = $this->container->getDefinition('graphql.execution_context');
        $definition->setAutowired(true);
        $this->container->setDefinition('graphql.execution_context', $definition);
    }

    private function processTaggedQueries()
    {
        $callback = function ($res) {
            $this->queries = array_merge($this->queries, $res);
        };
        $this->processByTag(GraphqlQueryInterface::TAG_MAME, $callback);
    }

    private function processTaggedMutations()
    {
        $callback = function ($res) {
            $this->mutations = array_merge($this->mutations, $res);
        };
        $this->processByTag(GraphqlMutationInterface::TAG_MAME, $callback);
    }

    /**
     * @param          $tag
     * @param callable $callback
     *
     * @throws ReflectionException
     */
    private function processByTag($tag, callable $callback)
    {
        $services = $this->container->findTaggedServiceIds($tag, true);
        $tagged_with_container = [];
        if ($services && count($services)) {
            foreach ($services as $id => $tags) {
                $def = $this->container->getDefinition($id);
                $class_string = '$this->container->get('.$id.'::class)';
                $cl = new ReflectionClass($id);
                if (!$cl->isSubclassOf(AbstractField::class)) {
                    continue;
                }
                if (($ann = $this->annotations_reader->getClassAnnotation($cl, MarkToRemove::class))) {
                    if ($ann->force) {
                        $class_string = '//'.$class_string;
                    }
                    $class_string .= $this->getCommentByAnnotation($ann);
                }
                $tagged_with_container[] = $class_string;
                $def->setPublic(true);
            }
            $callback($tagged_with_container);
        }
    }

    /**
     * @param $types
     *
     * @throws ReflectionException
     */
    private function processTypes($types)
    {
        $types_map = ClassMapGenerator::createMap($types);
        foreach ($types_map as $class => $path) {
            $reflected = new ReflectionClass($class);
            if ($reflected->implementsInterface(GraphqlTypeInterface::class)) {
                $this->validateConstructor($reflected);
                $this->addToContainer($class, ['graphql_type']);
            }
        }
    }

    /**
     * @param $path
     *
     * @return array
     * @throws ReflectionException
     */
    private function _getMutations($path)
    {
        $class_map = ClassMapGenerator::createMap($path);
        $mutations = [];
        foreach ($class_map as $class => $path) {
            $cl = new ReflectionClass($class);

            if ($cl->isAbstract()) {
                continue;
            }

            if (
                $cl->implementsInterface(GraphqlQueryInterface::class)
                || $cl->implementsInterface(GraphqlMutationInterface::class)
            ) {
                $this->validateConstructor($cl);
                $this->addToContainer($class, static::getTags($cl));
                continue;
            }

            if ($cl->implementsInterface(GraphqlQueryInterface::class)) {
                $this->validateConstructor($cl);
                $this->addToContainer($class, static::getTags($cl));
                continue;
            } elseif ($cl->implementsInterface(GraphqlMutationInterface::class)) {
                $this->validateConstructor($cl);
                $this->addToContainer($class, static::getTags($cl));
                continue;
            }


            if ($cl->isTrait() || $cl->isAbstract()) {
                continue;
            }
            $constructor = $cl->getConstructor();
            $arguments = $constructor->getParameters();
            $constructor_is_valid = (count($arguments) == 1 && $arguments[0]->isArray());

            if ($cl->isInstantiable() && $constructor_is_valid && $cl->isSubclassOf(AbstractField::class)) {
                $class_string = ' new '.$class.'()';
                if (($ann = $this->annotations_reader->getClassAnnotation($cl, MarkToRemove::class))) {
                    if ($ann->force) {
                        $class_string = '//'.$class_string;
                    }
                    $class_string .= $this->getCommentByAnnotation($ann);
                }
                $mutations[] = $class_string;
            }
        }

        return $mutations ?? [];
    }

    /**
     * @param ReflectionClass $cl
     *
     * @return array
     */
    private static function getTags(ReflectionClass $cl): array
    {
        $tags = [];
        if ($cl->implementsInterface(GraphqlQueryInterface::class)) {
            $tags[] = GraphqlQueryInterface::TAG_MAME;
        } elseif ($cl->implementsInterface(GraphqlMutationInterface::class)) {
            $tags[] = GraphqlMutationInterface::TAG_MAME;
        }

        return $tags;
    }

    /**
     * @param ReflectionClass $class
     */
    private function validateConstructor(ReflectionClass $class)
    {
        $constructor = $class->getConstructor();
        $filename = $constructor->getFileName();
        $start_line = $constructor->getStartLine() - 1; // it's actually - 1, otherwise you wont get the function() block
        $end_line = $constructor->getEndLine();
        $length = $end_line - $start_line;

        $source = file($filename);
        $body = implode("", array_slice($source, $start_line, $length));
        $body = preg_replace('/\s+/', '', $body);
        if ($constructor->getDeclaringClass() == $class) {
            $type = static::getType($class);
            if ((strpos($body, 'parent::__construct([])') === false)) {
                throw new RuntimeException($type.' '.$class->getName().' must call parent::__construct([])');
            } elseif ((strpos($body, '//parent::__construct([])') !== false) || (strpos($body, '/*parent::__construct([]);*/') !== false)) {
                throw new RuntimeException($type.' '.$class->getName().' must call parent::__construct([])');
            }
        }
    }

    /**
     * @param ReflectionClass $class
     *
     * @return string
     */
    private static function getType(ReflectionClass $class)
    {
        $map = [
            GraphqlQueryInterface::class    => 'Query',
            GraphqlMutationInterface::class => 'Mutation',
            GraphqlTypeInterface::class     => 'Type',
        ];
        foreach ($map as $k => $v) {
            if ($class->implementsInterface($k)) {
                return $v;
            }
        }

        return '';
    }

    /**
     * @param $class
     *
     * @return Definition
     */
    private function getDefinition($class)
    {
        if ($this->container->has($class)) {
            return $this->container->getDefinition($class);
        }

        return new Definition($class);
    }

    /**
     * @param       $class
     * @param array $tags
     */
    private function addToContainer($class, array $tags)
    {
        $definition = $this->getDefinition($class);
        $definition->setAutowired(true)
            ->setPrivate(false);
        foreach ($tags as $tag) {
            $definition->addTag($tag);
        }

        $this->container->setDefinition($class, $definition);
    }

    /**
     * @param $ann
     *
     * @return string
     */
    private function getCommentByAnnotation($ann)
    {
        $el = ['mark to remove'];
        if (isset($ann->version)) {
            $el[] = "version: {$ann->version}";
        }
        if (isset($ann->from)) {
            $el[] = " from: {$ann->from} ";
        }

        return '/*'.implode(' ', $el).'*/';
    }
}
