<?php

namespace App\Form\Marketplace;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Regex;

class EquipementRentalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product_id', HiddenType::class, [
                'mapped' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Équipement invalide.']),
                    new Regex([
                        'pattern' => '/^\d+$/',
                        'message' => 'Équipement invalide.',
                    ]),
                ],
            ])
            ->add('rental_price_per_day', NumberType::class, [
                'mapped' => false,
                'label' => 'Prix de location par jour (DT)',
                'scale' => 3,
                'html5' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le prix de location par jour est obligatoire.']),
                    new Positive(['message' => 'Le prix de location doit être supérieur à 0.']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex : 25',
                    'step' => '0.001',
                    'min' => '0.001',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('rental_description', TextareaType::class, [
                'mapped' => false,
                'label' => 'Description de la location',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 800,
                        'maxMessage' => 'La description ne doit pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ex : Équipement disponible à la journée, excellent état...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
