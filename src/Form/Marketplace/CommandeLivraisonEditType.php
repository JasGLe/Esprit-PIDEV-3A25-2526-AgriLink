<?php

namespace App\Form\Marketplace;

use App\Entity\Marketplace\Commandes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CommandeLivraisonEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => ['class' => 'form-control', 'maxlength' => 100],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => ['class' => 'form-control', 'maxlength' => 100],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'constraints' => [new NotBlank(['message' => 'Le téléphone est obligatoire.'])],
                'attr' => ['class' => 'form-control', 'maxlength' => 30],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'constraints' => [new NotBlank(['message' => 'L’adresse est obligatoire.'])],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('complement', TextType::class, [
                'label' => 'Complément',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => ['class' => 'form-control', 'maxlength' => 20],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'constraints' => [new NotBlank(['message' => 'La ville est obligatoire.'])],
                'attr' => ['class' => 'form-control', 'maxlength' => 100],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn mc-btn-refresh'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commandes::class,
        ]);
    }
}
