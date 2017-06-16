<?php

namespace AppBundle\Controller;


use AppBundle\Entity\Scenario;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

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
                $scenario->setCreatedAt(new \DateTime());
                $scenario->setCost([
                    'month' => 'Jan',
                    'cost' => [
                        'greenCost' => 123,
                        'classicCost' => 456
                    ]
                ]);

                $em->persist($scenario);
                $em->flush();

                return $this->render('AppBundle:dashboard:index.html.twig', array(
                    'form' => $form->createView(),
                    'scenario' => $scenario
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