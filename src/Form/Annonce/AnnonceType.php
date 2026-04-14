<?php

namespace App\Form\Annonce;

use App\Entity\Annonce\Annonce;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnnonceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label'      => 'Titre',
                'trim'       => true,
                'empty_data' => null,
                'required'   => true,
            ])
            ->add('type', TextType::class, [
                'label'      => 'Type de produit',
                'trim'       => true,
                'empty_data' => null,
                'required'   => true,
            ])
            ->add('quantite', IntegerType::class, [
                'label'    => 'Quantite',
                'required' => true,
            ])
            ->add('prixUnitaire', NumberType::class, [
                'label'    => 'Prix unitaire (TND)',
                'scale'    => 2,
                'required' => true,
            ])
            ->add('status', ChoiceType::class, [
                'label'       => 'Statut',
                'choices'     => [
                    'Active'   => 'ACTIVE',
                    'Inactive' => 'INACTIVE',
                    'Vendue'   => 'VENDUE',
                ],
                'placeholder' => 'Choisir un statut',
                'required'    => true,
            ])
            ->add('photos', TextType::class, [
                'label'      => 'URL des photos',
                'required'   => false,
                'trim'       => true,
                'empty_data' => null,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'     => Annonce::class,
            'csrf_protection' => true,
        ]);
    }
}