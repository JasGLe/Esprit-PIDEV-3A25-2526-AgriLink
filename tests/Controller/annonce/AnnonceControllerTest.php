<?php

namespace App\Tests\Controller\annonce;

use App\Controller\Annonce\AnnonceController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormError;

final class AnnonceControllerTest extends TestCase
{
    public function testCollectFormErrorsBuildsReadableDeduplicatedMessages(): void
    {
        $formFactory = Forms::createFormFactory();
        $form = $formFactory->createBuilder()
            ->add('titre')
            ->add('status')
            ->getForm();

        // Add repeated + field-specific errors to ensure output is unique and user friendly.
        $form->get('titre')->addError(new FormError('Ce champ est requis.'));
        $form->get('titre')->addError(new FormError('Ce champ est requis.'));
        $form->get('status')->addError(new FormError('Valeur invalide.'));

        $controller = new AnnonceController();
        $method = new \ReflectionMethod($controller, 'collectFormErrors');
        $method->setAccessible(true);

        /** @var array<int, string> $messages */
        $messages = $method->invoke($controller, $form);

        $this->assertContains('Titre: Ce champ est requis.', $messages);
        $this->assertContains('Statut: Valeur invalide.', $messages);
        $this->assertCount(2, $messages);
    }
}

