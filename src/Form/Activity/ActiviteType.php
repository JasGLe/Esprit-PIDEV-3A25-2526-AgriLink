<?php

namespace App\Form\Activity;

use App\Entity\Activity\Activite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', null, [
                'label' => 'Titre',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Semis de tomates',
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
            ->add('typeActivite', ChoiceType::class, [
                'label' => 'Type',
                'required' => true,
                'choices' => [
                    'Semis' => 'SEMIS',
                    'Irrigation' => 'IRRIGATION',
                    'Récolte' => 'RECOLTE',
                    'Traitement' => 'TRAITEMENT',
                    'Taille' => 'TAILLE',
                    'Autre' => 'AUTRE',
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
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de début',
                'required' => true,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'help' => 'Date/heure de début.',
                'constraints' => [
                    new Assert\NotNull([
                        'message' => 'La date de début est obligatoire.',
                    ]),
                ],
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date de fin (optionnelle)',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'help' => 'Doit être après la date début.',
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'required' => true,
                'choices' => [
                    'Planifiée' => 'PLANIFIEE',
                    'En cours' => 'EN_COURS',
                    'Terminée' => 'TERMINEE',
                ],
                'attr' => ['class' => 'form-select'],
                'help' => 'État actuel.',
                'placeholder' => '-- Choisissez un statut --',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le statut est obligatoire.',
                    ]),
                ],
            ])
            ->add('coutEstime', NumberType::class, [
                'label' => 'Coût estimé (DT)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00',
                    'step' => '0.01',
                    'min' => '0',
                ],
                'help' => 'Optionnel.',
                'constraints' => [
                    new Assert\PositiveOrZero([
                        'message' => 'Le coût doit être positif ou nul.',
                    ]),
                ],
            ]);

        if ($options['is_admin']) {
            $builder->add('idAgriculteur', IntegerType::class, [
                'label' => 'ID agriculteur',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ID de l\'agriculteur',
                    'min' => 1,
                ],
                'help' => 'Requis en mode admin.',
                'constraints' => [
                    new Assert\Positive([
                        'message' => 'L\'ID agriculteur doit être supérieur à 0.',
                    ]),
                ],
            ]);
        }

        // Custom validation: dateFin > dateDebut
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            if ($data instanceof Activite && $data->getDateFin() !== null && $data->getDateDebut() !== null) {
                if ($data->getDateFin() <= $data->getDateDebut()) {
                    $form->get('dateFin')->addError(
                        new FormError('La date de fin doit être après la date de début.')
                    );
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activite::class,
            'is_admin' => false,
            'attr' => [
                'data-turbo' => 'false', // Désactive Turbo pour ce formulaire
            ],
        ]);

        $resolver->setAllowedTypes('is_admin', 'bool');
    }
}