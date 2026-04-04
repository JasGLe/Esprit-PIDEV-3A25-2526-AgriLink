<?php

namespace App\Form\Activity;

use App\Entity\Activity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', null, [
                'label' => 'Titre',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Salon agricole 2026',
                ],
                'help' => 'Nom court et clair.',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le titre est obligatoire.',
                    ]),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 100,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description détaillée de l\'événement',
                ],
                'help' => 'Optionnel.',
                'constraints' => [
                    new Assert\Length([
                        'max' => 2000,
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('typeEvenement', ChoiceType::class, [
                'label' => 'Type',
                'required' => true,
                'choices' => [
                    'Officiel' => 'OFFICIEL',
                    'Personnel' => 'PERSONNEL',
                ],
                'attr' => ['class' => 'form-select'],
                'help' => 'Choisissez une catégorie.',
                'placeholder' => '-- Choisissez un type --',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le type est obligatoire.',
                    ]),
                ],
            ])
            ->add('dateEvenement', DateTimeType::class, [
                'label' => 'Date de l\'événement',
                'required' => true,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'help' => 'Date/heure de l\'événement.',
                'constraints' => [
                    new Assert\NotNull([
                        'message' => 'La date est obligatoire.',
                    ]),
                ],
            ])
            ->add('lieu', null, [
                'label' => 'Lieu',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Centre de conférences, Tunis',
                ],
                'help' => 'Adresse ou lieu principal.',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le lieu est obligatoire.',
                    ]),
                    new Assert\Length([
                        'max' => 150,
                        'maxMessage' => 'Le lieu ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ]);

        if ($options['is_admin']) {
            $builder->add('idOrganisateur', IntegerType::class, [
                'label' => 'ID organisateur',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ID de l\'organisateur',
                    'min' => 1,
                ],
                'help' => 'Requis en mode admin.',
                'constraints' => [
                    new Assert\Positive([
                        'message' => 'L\'ID organisateur doit être supérieur à 0.',
                    ]),
                ],
            ]);
        }

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
            'is_admin' => false,
            'attr' => [
                'data-turbo' => 'false', // Désactive Turbo pour ce formulaire
            ],
        ]);

        $resolver->setAllowedTypes('is_admin', 'bool');
    }
}