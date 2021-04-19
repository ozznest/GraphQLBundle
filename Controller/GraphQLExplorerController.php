<?php
/**
 * Date: 31.08.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Ozznest\GraphQLBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Youshido\GraphQLBundle\Controller\GraphQLExplorerController as BaseController;
class GraphQLExplorerController extends BaseController
{
    /**
     * @Route("/graphql/explorer")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function explorerAction()
    {
        $response = $this->render('@GraphQL/Feature/explorer.html.twig', [
            'graphQLUrl' => $this->generateUrl('youshido_graphql_graphql_default'),
            'tokenHeader' => 'access-token'
        ]);

        $date = \DateTime::createFromFormat('U', strtotime('tomorrow'), new \DateTimeZone('UTC'));
        $response->setExpires($date);
        $response->setPublic();
        return $response;
    }
}