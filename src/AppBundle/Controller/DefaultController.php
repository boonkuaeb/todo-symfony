<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     * @Method({"GET", "POST"})
     */
    public function indexAction(Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $task = new Task();
        $task->setCreatedAt(new \DateTime());

        $form = $this->createFormBuilder($task)
            ->setAction($this->generateUrl('homepage'))
            ->setMethod('POST')
            ->add('task', 'text')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em->persist($task);
            $em->flush();
            $this->addFlash('notice', 'Task added.');

            return $this->redirectToRoute('homepage');
        }

        $tasks = $em->getRepository('AppBundle:Task')->findAll();

        return $this->render('default/index.html.twig', [
            'form' => $form->createView(),
            'tasks' => $tasks
        ]);
    }

    /**
     * @Route("/change-status/{id}", name="task_done")
     * @Method({"POST"})
     */
    public function doneAction($id, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var Task $task */
        $task = $em->getRepository('AppBundle:Task')->find($id);

        if (!$task) {
            $this->addFlash('error', 'Task not found.');

            return $this->redirectToRoute('homepage');
        }

        $task->setDone(! $task->isDone());
        $em->flush();

        return $this->redirectToRoute('homepage');
    }

    /**
     * @Route("/remove/{id}", name="task_remove")
     * @Method({"GET"})
     */
    public function deleteAction($id, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        $task = $em->getRepository('AppBundle:Task')->find($id);

        if (!$task) {
            $this->addFlash('error', 'Task not found.');

            return $this->redirectToRoute('homepage');
        }

        $em->remove($task);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }
}
