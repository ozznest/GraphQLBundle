<?php
/**
 * Created by PhpStorm.
 * User: o.chuiko
 * Date: 21.01.2019
 * Time: 13:36
 */

namespace Ozznest\GraphQLBundle\Controller;



use Ozznest\GraphQLBundle\Event\BeforeGetGraphqlResponse;
use Ozznest\GraphQLBundle\Event\GraphqlSchemaCreateEvent;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Youshido\GraphQL\Exception\ConfigurationException;
use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQLBundle\Controller\GraphQLController as BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;


class GraphQLController  extends BaseController
{

    private  RequestStack $requestStack;
    private  EventDispatcherInterface $dispstcher;
    protected  ContainerInterface $container;

    public function __construct(RequestStack  $requestStack, EventDispatcherInterface $dispatcher, ContainerInterface $container)
    {
        $this->requestStack = $requestStack;
        $this->dispstcher = $dispatcher;
        $this->container = $container;
    }


    /**
     * @Route("/graphql")
     *
     * @throws ConfigurationException
     *
     * @return JsonResponse
     */
    public function defaultAction(){
        if ($this->requestStack->getCurrentRequest()->getMethod() == 'OPTIONS') {
            return $this->createEmptyResponse();
        }

        list($queries, $isMultiQueryRequest) = $this->getPayload();

        $schemaClass = $this->getParameter('graphql.schema_class');
        if (!$schemaClass || !class_exists($schemaClass)) {
            return new JsonResponse([['message' => 'Schema class ' . $schemaClass . ' does not exist']], 200, $this->getParameter('graphql.response.headers'));
        }

        if (!$this->container->initialized('graphql.schema')) {
            $schema = new $schemaClass();
            if ($schema instanceof ContainerAwareInterface) {
                $schema->setContainer($this->container);
            }
            $this->container->set('graphql.schema', $schema);
            $this->dispstcher->dispatch(new GraphqlSchemaCreateEvent($schema));

        }

        $queryResponses = array_map(function($queryData) {
            return $this->executeQuery($queryData['query'], $queryData['variables']);
        }, $queries);

        $response = new JsonResponse($isMultiQueryRequest ? $queryResponses : $queryResponses[0], 200, $this->getParameter('graphql.response.headers'));

        if ($this->getParameter('graphql.response.json_pretty')) {
            $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRETTY_PRINT);
        }

        return $response;
    }

    private function createEmptyResponse()
    {
        return new JsonResponse([], 200, $this->getParameter('graphql.response.headers'));
    }

    private function executeQuery($query, $variables)
    {
        /** @var Processor $processor */
        $processor = $this->get('graphql.processor');

        $processor->processPayload($query, $variables);
        $response = $processor->getResponseData();
        $this->dispstcher->dispatch(new BeforeGetGraphqlResponse($query, $variables, $response));
        return $response;
    }

    private function getPayload()
    {
        $request   = $this->get('request_stack')->getCurrentRequest();
        $query     = $request->get('query', null);
        $variables = $request->get('variables', []);
        $isMultiQueryRequest = false;
        $queries = [];

        $variables = is_string($variables) ? json_decode($variables, true) ?: [] : [];

        $content = $request->getContent();
        if (!empty($content)) {
            if ($request->headers->has('Content-Type') && 'application/graphql' == $request->headers->get('Content-Type')) {
                $queries[] = $content;
            } else {
                $params = json_decode($content, true);

                if ($params) {
                    // check for a list of queries
                    if (isset($params[0]) === true) {
                        $isMultiQueryRequest = true;
                    } else {
                        $params = [$params];
                    }

                    foreach ($params as $queryParams) {
                        $query = isset($queryParams['query']) ? $queryParams['query'] : $query;

                        if (isset($queryParams['variables'])) {
                            if (is_string($queryParams['variables'])) {
                                $variables = json_decode($queryParams['variables'], true) ?: $variables;
                            } else {
                                $variables = $queryParams['variables'];
                            }

                            $variables = is_array($variables) ? $variables : [];
                        }

                        $queries[] = [
                            'query' => $query,
                            'variables' => $variables,
                        ];
                    }
                }
            }
        } else {
            $queries[] = [
                'query' => $query,
                'variables' => $variables,
            ];
        }

        return [$queries, $isMultiQueryRequest];
    }

}