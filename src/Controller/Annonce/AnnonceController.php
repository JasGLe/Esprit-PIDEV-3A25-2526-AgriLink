<?php

namespace App\Controller\Annonce;

use App\Entity\Annonce\Annonce;
use App\Form\Annonce\AnnonceType;
use App\Repository\Annonce\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/annonces')]
class AnnonceController extends AbstractController
{
    #[Route('/', name: 'annonce_index', methods: ['GET'])]
    public function index(Request $request, AnnonceRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'recent');

        return $this->render('Annonce/index.html.twig', [
            'annonces' => $repo->findForIndex($search, $sort),
            'filters' => [
                'q' => $search,
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/new', name: 'annonce_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $annonce = new Annonce();
        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($annonce);
                $em->flush();
                $this->addFlash('success', 'Annonce creee avec succes.');

                return $this->redirectToRoute('annonce_index');
            } catch (\Throwable $exception) {
                $form->addError(new FormError('La creation de l annonce a echoue. Verifiez les donnees saisies puis reessayez.'));
                $this->addFlash('danger', 'La creation de l annonce a echoue.');
            }
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
            try {
                $em->flush();
                $this->addFlash('success', 'Annonce mise a jour.');

                return $this->redirectToRoute('annonce_index');
            } catch (\Throwable $exception) {
                $form->addError(new FormError('La mise a jour de l annonce a echoue. Verifiez les donnees saisies puis reessayez.'));
                $this->addFlash('danger', 'La mise a jour de l annonce a echoue.');
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($this->collectFormErrors($form) as $error) {
                $this->addFlash('warning', $error);
            }
            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire avant de continuer.');
        }

        return $this->render('Annonce/edit.html.twig', [
            'annonce' => $annonce,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'annonce_delete', methods: ['POST'])]
    public function delete(Request $request, Annonce $annonce, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $annonce->getId(), $request->request->get('_token'))) {
            $em->remove($annonce);
            $em->flush();
            $this->addFlash('success', 'Annonce supprimee.');
        }

        return $this->redirectToRoute('annonce_index');
    }

    /**
     * Collecte tous les messages d'erreur du formulaire
     * @return string[]
     */
    private function collectFormErrors(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $name = $origin instanceof FormInterface ? $origin->getName() : null;
            
            // Traduction des noms de champs pour les messages d'erreur
            $fieldNames = [
                'titre' => 'Titre',
                'type' => 'Type de produit',
                'quantite' => 'Quantite',
                'prixUnitaire' => 'Prix unitaire',
                'status' => 'Statut',
                'photos' => 'Photos'
            ];
            
            $displayName = $name && isset($fieldNames[$name]) ? $fieldNames[$name] : ($name ? ucfirst($name) : null);
            
            $messages[] = $displayName
                ? sprintf('%s: %s', $displayName, $error->getMessage())
                : $error->getMessage();
        }

        return array_values(array_unique($messages));
    }
}