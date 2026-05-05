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

/**
 * EquipementType
 * ─────────────────────────────────────────────────────────────────────────────
 * Formulaire Symfony pour la création et la modification d'un équipement.
 *
 * Particularités techniques :
 *
 * 1. Champ "type" dynamique (dépend de "categorie") :
 *    Le champ <select> "Type" est peuplé dynamiquement selon la catégorie choisie.
 *    Deux listes distinctes : TYPES_VEHICULES (pour "Véhicule Motorisé")
 *    et TYPES_EQUIPEMENTS (pour toutes les autres catégories).
 *    Cela nécessite deux listeners d'événements :
 *      - PRE_SET_DATA : reconfigure le champ type avec les choices corrects
 *        quand le formulaire est ouvert en édition (entité déjà chargée).
 *      - PRE_SUBMIT   : reconfigure le champ type selon ce que l'utilisateur
 *        a soumis, pour que Symfony accepte la valeur sans "invalid choice error".
 *
 * 2. Champ "imageFile" (non mappé) :
 *    Ce champ n'est pas mappé directement sur l'entité ('mapped' => false).
 *    Il est géré manuellement dans EquipementController via FileUploader::upload().
 *    Contraintes : max 2 Mo, types MIME image/jpeg + image/png + image/webp.
 *
 * 3. Champs véhicule (kilométrage, heures) :
 *    Ces champs sont toujours présents dans le formulaire HTML mais masqués/
 *    affichés via JavaScript selon la catégorie sélectionnée. Côté serveur,
 *    le contrôleur les efface si la catégorie n'est pas "Véhicule Motorisé".
 *
 * 4. Champs lat/lng (cachés) :
 *    Remplis automatiquement par la carte Leaflet + l'API OpenCage quand
 *    l'utilisateur place un marqueur sur la carte de géolocalisation.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EquipementType extends AbstractType
{
    /**
     * Construction du formulaire avec tous les champs de l'équipement.
     *
     * @param FormBuilderInterface      $builder  Constructeur de formulaire Symfony
     * @param array<string, mixed>      $options  Options du formulaire (data_class uniquement) // Fix PHPStan
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Informations générales ───────────────────────────────────────
            ->add('nom', TextType::class, [
                'label'    => 'Nom de l\'équipement',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Ex: Tracteur John Deere T5000',
                    'class'       => 'form-control',
                ],
            ])

            // Catégorie = "Véhicule Motorisé" ou autre — détermine les choices de 'type'
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

            // Type initial vide — sera peuplé par PRE_SET_DATA (edit) ou JS + PRE_SUBMIT (new)
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

            // ── Photo (non mappée — gérée manuellement par FileUploader) ────
            ->add('imageFile', FileType::class, [
                'label'    => 'Photo de l\'équipement',
                'required' => false,
                'mapped'   => false, // géré manuellement dans EquipementController
                'attr'     => ['class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize'          => '2M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Formats acceptés : JPG, PNG, WEBP (max 2Mo).',
                    ]),
                ],
            ])

            // ── Champs véhicule (affichés/masqués via JS, effacés côté serveur si non-véhicule) ──
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
                'data'     => 10000, // valeur par défaut : 10 000 km
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
                'data'     => 200, // valeur par défaut : 200 h
                'attr'     => [
                    'min'   => 1,
                    'class' => 'form-control',
                ],
            ])

            // Seuil en jours applicable à tous les équipements (pas seulement véhicules)
            ->add('seuilJoursMaintenance', IntegerType::class, [
                'label'    => 'Seuil maintenance (jours)',
                'required' => false,
                'data'     => 365, // valeur par défaut : 1 an
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

            // ── Localisation (remplis automatiquement par JS / Leaflet + OpenCage) ──
            ->add('latitude', HiddenType::class, [
                'required' => false,
                'attr'     => ['id' => 'equipement_latitude'],
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
                'attr'     => ['id' => 'equipement_longitude'],
            ]);

        // ── Listener PRE_SET_DATA : peuple 'type' selon la catégorie de l'entité (mode édition) ──
        // Déclenché quand le formulaire reçoit les données de l'entité existante.
        // Sans ce listener, le champ type serait vide (choices = []) en mode édition
        // et la valeur actuelle serait rejetée comme "invalid choice".
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

        // ── Listener PRE_SUBMIT : recalcule 'type' selon les données soumises ──
        // Déclenché AVANT la validation, pour que Symfony accepte la valeur soumise
        // sans lever "invalid choice" (car les choices de type dépendent de la catégorie soumise).
        // Sans ce listener, toute soumission avec type ≠ '' serait invalide.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data      = $event->getData();
            $form      = $event->getForm();

            // Catégorie soumise (ou défaut si vide pour éviter un conflit)
            $categorie = (isset($data['categorie']) && $data['categorie'] !== '')
                ? $data['categorie']
                : 'Autre Équipement';

            $choices = $this->getTypesForCategorie($categorie);

            $form->add('type', ChoiceType::class, [
                'label'           => 'Type',
                'choices'         => $choices,
                'placeholder'     => '-- Choisir un type --',
                'required'        => false, // Assert\NotBlank dans l'entité gère l'obligatoire
                'invalid_message' => 'Veuillez choisir un type.',
                'attr'            => [
                    'class' => 'form-select',
                    'id'    => 'equipement_type',
                ],
            ]);
        });
    }

    /**
     * Retourne les choices de types selon la catégorie sélectionnée.
     *
     * Utilisé par les deux listeners (PRE_SET_DATA et PRE_SUBMIT) pour
     * centraliser la logique de sélection de la liste de types.
     *
     * @param string $categorie  La catégorie sélectionnée
     *
     * @return array<string, string>  Constante TYPES_VEHICULES ou TYPES_EQUIPEMENTS selon la catégorie // Fix PHPStan
     */
    private function getTypesForCategorie(string $categorie): array
    {
        return $categorie === 'Véhicule Motorisé'
            ? Equipement::TYPES_VEHICULES
            : Equipement::TYPES_EQUIPEMENTS;
    }

    /**
     * Configure les options du formulaire.
     *
     * Lie le formulaire à l'entité Equipement pour le mapping automatique
     * des champs vers les propriétés de l'entité.
     *
     * @param OptionsResolver $resolver  Résolveur d'options Symfony
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
        ]);
    }
}
