<?php

namespace App\Form\Marketplace;

use App\Entity\Marketplace\RentalRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class EquipmentRentalRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('produit_id', HiddenType::class, [
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Le produit cible est obligatoire. Veuillez re-cliquer sur "Demander location".'),
                    new Regex('/^\d+$/'),
                ],
            ])
            ->add('fullName', TextType::class, ['label' => 'Nom complet / Raison sociale', 'constraints' => [new NotBlank(), new Length(max: 200)]])
            ->add('email', EmailType::class, ['label' => 'Email', 'constraints' => [new NotBlank()]])
            ->add('phone', TextType::class, ['label' => 'Téléphone', 'constraints' => [new NotBlank(), new Length(max: 30)]])
            ->add('dateNaissance', DateType::class, ['label' => 'Date de naissance', 'required' => false, 'widget' => 'single_text'])
            ->add('address', TextType::class, ['label' => 'Adresse', 'constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('identityNumber', TextType::class, ['label' => 'CIN / Passeport / Registre', 'constraints' => [new NotBlank(), new Length(max: 80)]])
            ->add('rentalStartAt', DateTimeType::class, ['label' => 'Début location', 'widget' => 'single_text', 'constraints' => [new NotBlank()]])
            ->add('rentalEndAt', DateTimeType::class, ['label' => 'Fin location', 'widget' => 'single_text', 'constraints' => [new NotBlank()]])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices' => ['Espèces' => 'cash', 'Virement bancaire' => 'bank_transfer', 'Carte' => 'card'],
                'constraints' => [new NotBlank()],
            ])
            ->add('usageLocation', TextType::class, ['label' => 'Lieu d’utilisation', 'constraints' => [new NotBlank(), new Length(max: 255)]])
            ->add('transportResponsibility', ChoiceType::class, [
                'label' => 'Responsable du transport',
                'choices' => ['Locataire' => 'renter', 'Vendeur' => 'seller'],
                'constraints' => [new NotBlank()],
            ])
            ;

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            /** @var RentalRequest $data */
            $data = $event->getData();
            if (!$data) {
                return;
            }
            if ($data->getRentalEndAt() <= $data->getRentalStartAt()) {
                $form->get('rentalEndAt')->addError(new FormError('La fin doit être après le début.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RentalRequest::class]);
    }
}
