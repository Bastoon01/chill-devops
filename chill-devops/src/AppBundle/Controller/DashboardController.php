<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Configuration;
use AppBundle\Entity\Scenario;
use AppBundle\Services\ScenarioResult;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class DashboardController extends Controller
{
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */

    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $scenario = new Scenario();
        $form = $this->createFormBuilder($scenario)
            ->add('name', TextType::class, array('label' => "Nom du scénario"))
            ->add('clientStart', IntegerType::class, array('label' => "Nombre d'utilisateurs"))
            ->add('periodicity', IntegerType::class, array('label' => "Périodicité"))
            ->add( 'clientAdd', IntegerType::class, array('label' => "Pourcentage d'utilisateurs"))
            ->getForm();

        $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /** @var Scenario $scenario */
                $scenario = $form->getData();
                $totalClient = $this->get('app_dashboard_scenario_result')->getTotalClientsByPeriodicity($scenario);

                if ($totalClient[ScenarioResult::TEST_DURATION] > ScenarioResult::MAX_CHARGE) {
                    $this->addFlash(
                        'error',
                        'Vous avez dépassé la charge totale (5 000 000)'
                    );
                    return $this->render('AppBundle:dashboard:index.html.twig', array(
                        'form' => $form->createView(),
                    ));
                }

                $result = $this->get('app_dashboard_scenario_result')->getPricesAndServers($scenario);
                $servers = $this->get('app_dashboard_scenario_result')->getServers();
                $infoServers = [];
                foreach ($servers as $key => $value) {
                    $infoServers[] = $this->get('app_dashboard_scenario_result')->getInfoServer($key);
                }
                $datas = [];
                foreach ($result as $key => $value){
                    array_push($datas, $value);
                }

                $datas = json_encode($datas);
                $totalPrice = $this->get('app_dashboard_scenario_result')->getTotalPrice();
                $totalGreenPrice = $this->get('app_dashboard_scenario_result')->getTotalGreenPrice();
                $scenario->setCost($datas);
                $scenario->setTotalPrices([$totalPrice,$totalGreenPrice]);
                $serverInfos = [];
                $count = 1;
                foreach ($servers as $key => $value) {
                    if (!empty($value)){
                        $serverInfos[] = $em->getRepository(Configuration::class)->find($count);
                    }
                    $count++;
                }
                $scenario->setServers($serverInfos);
                $em->persist($scenario);
                $em->flush();

                return $this->render('AppBundle:dashboard:index.html.twig', array(
                    'form' => $form->createView(),
                    'scenario' => $scenario,
                    'result' => $result,
                    'data' => $datas,
                    'servers' => $servers,
                    'infoServers' => $infoServers,
                    'totalPrice' => $totalPrice,
                    'totalGreenPrice' => $totalGreenPrice,
                ));
            }

        return $this->render('AppBundle:dashboard:index.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function updateServerAction(Request $request) {
        $em = $this->getDoctrine()->getManager();
        $servers = $em->getRepository('AppBundle:Configuration');
        $datas = $request->request->all();
        $server1 = $servers->findOneById('1');
        $server2 = $servers->findOneById('2');
        $server3 = $servers->findOneById('3');

        if(!empty($datas['server1'])) {

           $server1->setRam($datas['server1']['ram']);
           $server1->setCore($datas['server1']['core']);
           $server1->setDisk($datas['server1']['disk']);
           $em->persist($server1);
           $em->flush();
           $server1 = true;
        }
        if(!empty($datas['server2'])) {
            $server2->setRam($datas['server2']['ram']);
            $server2->setCore($datas['server2']['core']);
            $server2->setDisk($datas['server2']['disk']);
            $em->persist($server2);
            $em->flush();
            $server2 = true;

        }
        if(!empty($datas['server3'])) {
            $server3->setRam($datas['server3']['ram']);
            $server3->setCore($datas['server3']['core']);
            $server3->setDisk($datas['server3']['disk']);
            $em->persist($server3);
            $em->flush();
            $server3 = true;

        }
        if ($server1 == true && $server2 && $server3) {
            return new JsonResponse(true);
        }
        return new JsonResponse(false);
    }

    public function historyAction() {
        $em = $this->getDoctrine()->getManager();

        $scenarios = $em->getRepository('AppBundle:Scenario')->findAll();
        $bookmarks = $this->showBookmarksAction();

        return $this->render('AppBundle:dashboard:history.html.twig', array(
            'scenarios' => $scenarios,
            'favorites' => $bookmarks
        ));
    }

    public function showAction(Scenario $scenario)
    {
        $deleteForm = $this->createDeleteForm($scenario);

        $totalPrice = $scenario->getTotalPrices()[0];
        $totalGreenPrice = $scenario->getTotalPrices()[1];
        $servers = $scenario->getServers();

        $infoServers = [];
        $count = 1;
        foreach ($servers as $server) {
            $infoServers[$count] = $this->get('app_dashboard_scenario_result')->getInfoServer($server->getLabel());
            $count++;
        }

        return $this->render('AppBundle:dashboard:show.html.twig', array(
            'scenario' => $scenario,
            'delete_form' => $deleteForm->createView(),
            'totalPrice' => $totalPrice,
            'totalGreenPrice' => $totalGreenPrice,
            'servers' => $servers,
            'infoServers' => $infoServers,
        ));
    }

    public function editInputAction (Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $scenarioRepository = $em->getRepository('AppBundle:Scenario');

        if($request->isXmlHttpRequest()) {
            $entity = $scenarioRepository->findOneById($request->get('id'));
            if(!empty($entity)){
                $entity->setName($request->get('name'));
                $em->persist($entity);
                $em->flush();
            }
        }

        return new JsonResponse();
    }

    private function createDeleteForm(Scenario $scenario)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('scenario_delete', array('id' => $scenario->getId())))
            ->setMethod('DELETE')
            ->getForm()
            ;
    }

    public function deleteAction(Scenario $scenario, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($scenario);
        $em->flush();

        return $this->redirectToRoute($request->get('route'));
    }

    public function deleteSelectionAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $scenarioRepository = $em->getRepository('AppBundle:Scenario');

        if($request->isXmlHttpRequest() && !empty($request->get('tab'))) {
            $scenarioTable = $request->get('tab');
            foreach ($scenarioTable as $scenario) {
                $entity = $scenarioRepository->findOneById($scenario);
                if(!empty($entity)){
                    $em->remove($entity);
                    $em->flush();
                }
            }
        }
        return new JsonResponse();
    }

    /**
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function addBookmarkAction(Request $request, $id)
    {
      $em = $this->getDoctrine()->getManager();
      $referer = $request->headers->get('referer');
      $scenario = $em->getRepository('AppBundle:Scenario')->findOneById($id);
      $scenario->setIsBookmarked(true);
      $em->flush();
        if (substr($referer, -1) == "/") {

            return $this->redirectToRoute('scenario_show', [
                'id' => $id
            ]);
        } else {
            return $this->redirect($referer);
        }

    }

    /**
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteBookmarkAction(Request $request, $id)
    {
      $em = $this->getDoctrine()->getManager();
      $referer = $request->headers->get('referer');
      $scenario = $em->getRepository('AppBundle:Scenario')->findOneById($id);
      $scenario->setIsBookmarked(false);
      $em->flush();

      return $this->redirect($referer);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showBookmarksAction() {
      $em = $this->getDoctrine()->getManager();
      $bookmarksList = $em->getRepository('AppBundle:Scenario')->findBy(array("isBookmarked"=>true));
      return $bookmarksList;
//      return $this->render("AppBundle:dashboard:favorites.html.twig", array('scenarios' => $bookmarksList));
    }

    public function searchAction(Request $request)
    {
        $tabScenarios = [];
        $em = $this->getDoctrine()->getManager();

        $scenarioRepository = $em->getRepository('AppBundle:Scenario');

        if($request->isXmlHttpRequest()) {
            $scenarios = $scenarioRepository->searchScenario($request->get('data'));

            foreach ($scenarios as $scenario) {
                array_push($tabScenarios, array(
                    'id' => $scenario->getId(),
                    'name' => $scenario->getName()
                ));
            }
        }

        return new JsonResponse($tabScenarios);
    }

    public function isAvailableAction(Request $request){
        $datas = $request->request->all();
        $totalClients = $this->get('app_dashboard_check_available')->isAvailable($datas);
        if ($totalClients == true){
            $data = $totalClients;
            return new JsonResponse(true);
        }
        return new JsonResponse(false);
    }
}