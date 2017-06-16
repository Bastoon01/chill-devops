<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Configuration;
use AppBundle\Entity\Scenario;
use AppBundle\Services\ScenarioResult;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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

                /*TODO - SEND SIMULATION*/
                /** @var Scenario $scenario */
                $scenario = $form->getData();
                $totalClient = $this->get('app_dashboard_scenario_result')->getTotalClientsByPeriodicity($scenario);

                if ($totalClient[ScenarioResult::TEST_DURATION] > ScenarioResult::MAX_CHARGE) {
                    $this->addFlash(
                        'error',
                        'You\'ve reached the loading limit.'
                    );
                    return $this->render('AppBundle:dashboard:index.html.twig', array(
                        'form' => $form->createView(),
                    ));
                }

                $result = $this->get('app_dashboard_scenario_result')->getPricesAndServers($scenario);
                $servers = $this->get('app_dashboard_scenario_result')->getServers();
                $infoServers = [];
                foreach ($servers as $key => $value) {
                    $infoServers[$key] = $this->get('app_dashboard_scenario_result')->getInfoServer($key);
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

    public function historyAction() {
        $em = $this->getDoctrine()->getManager();

        $scenarios = $em->getRepository('AppBundle:Scenario')->findAll();

        return $this->render('AppBundle:dashboard:history.html.twig', array(
            'scenarios' => $scenarios,
        ));
    }

    public function showAction(Scenario $scenario)
    {
        $deleteForm = $this->createDeleteForm($scenario);

        return $this->render('AppBundle:dashboard:show.html.twig', array(
            'scenario' => $scenario,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    public function editAction(Request $request, Scenario $scenario)
    {
        $deleteForm = $this->createDeleteForm($scenario);
        $editForm = $this->createForm('AppBundle\Form\ScenarioType', $scenario);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid())    {
            $this->getDoctrine()->getManager()->flush();

            $em = $this->getDoctrine()->getManager();
            $scenarios = $em->getRepository('AppBundle:Scenario')->findAll();

            return $this->render('AppBundle:dashboard:history.html.twig', array(
                'scenarios' => $scenarios,
            ));
        }

        return $this->render('AppBundle:dashboard:edit.html.twig', array(
            'scenario' => $scenario,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
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

    public function deleteAction(Request $request, Scenario $scenario)
    {
        $em = $this->getDoctrine()->getManager();
        $scenarioRepo = $em->getRepository('AppBundle:Scenario');
        $scenarioToRemove = $scenarioRepo->findOneById($scenario);
        try {
            $em->remove($scenarioToRemove);
            $em->flush();
            $request->getSession()->getFlashBag()->add('success', 'Scenario supprimé');
        } catch (\Doctrine\DBAL\DBALException $e){
        $request->getSession()->getFlashBag()->add('danger', 'Erreur lors de la suppression :'
        . PHP_EOL . $e->getMessage());
        }

        return $this->redirectToRoute('scenario_history');
    }

    private function createDeleteForm(Scenario $scenario)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('scenario_delete', array('id' => $scenario->getId())))
            ->setMethod('DELETE')
            ->getForm()
            ;
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
}