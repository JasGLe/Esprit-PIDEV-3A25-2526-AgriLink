<?php

namespace App\Controller\Annonce;

use App\Entity\Annonce\Annonce;
use App\Form\Annonce\AnnonceType;
use App\Repository\Annonce\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/annonces')]
class AnnonceController extends AbstractController
{
    #[Route('/', name: 'annonce_index', methods: ['GET'])]
    public function index(AnnonceRepository $repo): Response
    {
        return $this->render('Annonce/index.html.twig', [
            'annonces' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'annonce_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $annonce = new Annonce();
        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($annonce);
            $em->flush();
            $this->addFlash('success', 'Annonce créée avec succès.');
            return $this->redirectToRoute('annonce_index');
        }

        return $this->render('Annonce/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'annonce_show', methods: ['GET'])]
    public function show(Annonce $annonce): Response
    {
        return $this->render('Annonce/show.html.twig', [
            'annonce' => $annonce,
        ]);
    }

    #[Route('/{id}/edit', name: 'annonce_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Annonce $annonce, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Annonce modifiée.');
            return $this->redirectToRoute('annonce_index');
        }

        return $this->render('Annonce/edit.html.twig', [
            'annonce' => $annonce,
            'form'    => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'annonce_delete', methods: ['POST'])]
    public function delete(Request $request, Annonce $annonce, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$annonce->getId(), $request->request->get('_token'))) {
            $em->remove($annonce);
            $em->flush();
            $this->addFlash('success', 'Annonce supprimée.');
        }
        return $this->redirectToRoute('annonce_index');
    }
}