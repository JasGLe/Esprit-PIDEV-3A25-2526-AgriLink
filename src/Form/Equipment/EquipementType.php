<?php

namespace App\Form\Equipment;

use App\Entity\Equipement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Infos générales ──────────────────────────────────────────────
            ->add('nom', TextType::class, [
                'label'    => 'Nom de l\'équipement',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Ex: Tracteur John Deere T5000',
                    'class'       => 'form-control',
                ],
            ])

            ->add('categorie', ChoiceType::class, [
    'label'           => 'Catégorie',
    'choices'         => Equipement::CATEGORIES,
    'placeholder'     => '-- Choisir une catégorie --',
    'required'        => false,
    'invalid_message' => 'Veuillez choisir une catégorie.',
    'attr'            => [
        'class' => 'form-select',
        'id'    => 'equipement_categorie',
    ],
])

->add('type', ChoiceType::class, [
    'label'           => 'Type',
    'choices'         => [],
    'placeholder'     => '-- Choisir un type --',
    'invalid_message' => 'Veuillez choisir un type.',
    'attr'            => [
        'class' => 'form-select',
        'id'    => 'equipement_type',
    ],
])

            ->add('marque', TextType::class, [
                'label'    => 'Marque',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Ex: John Deere',
                    'class'       => 'form-control',
                ],
            ])

            ->add('modele', TextType::class, [
                'label'    => 'Modèle',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Ex: T5000',
                    'class'       => 'form-control',
                ],
            ])

            ->add('dateAcquisition', DateType::class, [
                'label'    => 'Date d\'acquisition',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])

            ->add('statut', ChoiceType::class, [
                'label'       => 'Statut',
                'choices'     => Equipement::STATUTS,
                'placeholder' => '-- Choisir un statut --',
                'required'    => false,
                'attr'        => ['class' => 'form-select'],
            ])

            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => [
                    'rows'        => 3,
                    'placeholder' => 'Description de l\'équipement...',
                    'class'       => 'form-control',
                ],
            ])

            ->add('imageFile', FileType::class, [
                'label'    => 'Photo de l\'équipement',
                'required' => false,
                'mapped'   => false, // géré manuellement dans le Controller
                'attr'     => ['class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize'          => '2M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Formats acceptés : JPG, PNG, WEBP (max 2Mo).',
                    ]),
                ],
            ])

            // ── Champs véhicule (affichés/masqués via JS dans le template) ──
            ->add('kilometrageActuel', IntegerType::class, [
                'label'    => 'Kilométrage actuel (km)',
                'required' => false,
                'attr'     => [
                    'min'   => 0,
                    'class' => 'form-control',
                ],
            ])

            ->add('kilometrageDerniereMaintenance', IntegerType::class, [
                'label'    => 'Kilométrage à la dernière maintenance (km)',
                'required' => false,
                'attr'     => [
                    'min'   => 0,
                    'class' => 'form-control',
                ],
            ])

            ->add('seuilKmMaintenance', IntegerType::class, [
                'label'    => 'Seuil maintenance (km)',
                'required' => false,
                'data'     => 10000,
                'attr'     => [
                    'min'   => 1,
                    'class' => 'form-control',
                ],
            ])

            ->add('heuresUtilisation', IntegerType::class, [
                'label'    => 'Heures d\'utilisation',
                'required' => false,
                'attr'     => [
                    'min'   => 0,
                    'class' => 'form-control',
                ],
            ])

            ->add('heuresDerniereMaintenance', IntegerType::class, [
                'label'    => 'Heures à la dernière maintenance',
                'required' => false,
                'attr'     => [
                    'min'   => 0,
                    'class' => 'form-control',
                ],
            ])

            ->add('seuilHeuresMaintenance', IntegerType::class, [
                'label'    => 'Seuil maintenance (heures)',
                'required' => false,
                'data'     => 200,
                'attr'     => [
                    'min'   => 1,
                    'class' => 'form-control',
                ],
            ])

            ->add('seuilJoursMaintenance', IntegerType::class, [
                'label'    => 'Seuil maintenance (jours)',
                'required' => false,
                'data'     => 365,
                'attr'     => [
                    'min'   => 1,
                    'class' => 'form-control',
                ],
            ])

            ->add('dateDerniereMaintenance', DateType::class, [
                'label'    => 'Date de la dernière maintenance',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])

            // ── Localisation (remplis par JS / Leaflet) ──────────────────────
            ->add('latitude', HiddenType::class, [
                'required' => false,
                'attr'     => ['id' => 'equipement_latitude'],
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
                'attr'     => ['id' => 'equipement_longitude'],
            ]);

        // ── PRE_SET_DATA : peuple 'type' selon la catégorie de l'objet en édition ──
        // Utilisé quand on ouvre le formulaire edit (l'entité est déjà chargée)
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $equipement = $event->getData();
            $form       = $event->getForm();

     $categorie = ($equipement !== null && $equipement->getCategorie() !== null)
    ? $equipement->getCategorie()
    : 'Autre Équipement';
            $choices   = $this->getTypesForCategorie($categorie);

            $form->add('type', ChoiceType::class, [
                'label'       => 'Type',
                'choices'     => $choices,
                'placeholder' => '-- Choisir un type --',
                'attr'        => [
                    'class' => 'form-select',
                    'id'    => 'equipement_type',
                ],
            ]);
        });

        // ── PRE_SUBMIT : recalcule 'type' selon ce que l'utilisateur a soumis ──
        // Indispensable pour que Symfony accepte la valeur soumise sans erreur "invalid choice"
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
    $data      = $event->getData();
    $form      = $event->getForm();

    // Si catégorie vide, on met le défaut pour éviter le conflit
    $categorie = (isset($data['categorie']) && $data['categorie'] !== '')
        ? $data['categorie']
        : 'Autre Équipement';

    $choices = $this->getTypesForCategorie($categorie);

    $form->add('type', ChoiceType::class, [
        'label'           => 'Type',
        'choices'         => $choices,
        'placeholder'     => '-- Choisir un type --',
        'required'        => false, // ← on laisse Assert\NotBlank gérer
        'invalid_message' => 'Veuillez choisir un type.',
        'attr'            => [
            'class' => 'form-select',
            'id'    => 'equipement_type',
        ],
    ]);
});
    }

    // ── Retourne les choices selon la catégorie ──────────────────────────────
    private function getTypesForCategorie(string $categorie): array
    {
        return $categorie === 'Véhicule Motorisé'
            ? Equipement::TYPES_VEHICULES
            : Equipement::TYPES_EQUIPEMENTS;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
        ]);
    }
}