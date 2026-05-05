<?php

namespace App\Form\Equipment;

use App\Entity\Maintenance;
use App\Repository\EquipementRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * MaintenanceType
 * ─────────────────────────────────────────────────────────────────────────────
 * Formulaire Symfony pour la création et la modification d'une maintenance.
 *
 * Particularité principale — option 'user_id' obligatoire :
 *  Le formulaire requiert l'ID de l'utilisateur connecté (option 'user_id')
 *  pour filtrer la liste des équipements dans le champ <select> "Équipement concerné".
 *  Cela garantit qu'un agriculteur ne peut associer une maintenance qu'à SES équipements
 *  et non à ceux d'un autre utilisateur.
 *
 * Champ 'equipementId' (ChoiceType) :
 *  Ce champ est un entier (id de l'équipement) rendu comme un <select> avec
 *  des options "Nom — Type" => id. C'est une FK non-ORM-mappée : Doctrine ne
 *  connaît pas la relation directe, le contrôleur charge l'entité séparément.
 *
 * Champ 'cout' (NumberType) :
 *  Rendu comme un input texte avec scale=2 (deux décimales) et html5=false
 *  (évite le comportement du navigateur sur les <input type="number"> qui peut
 *  rejeter les virgules selon la locale du navigateur).
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MaintenanceType extends AbstractType
{
    /**
     * @param EquipementRepository $equipementRepo  Pour charger les équipements de l'utilisateur
     */
    public function __construct(
        private EquipementRepository $equipementRepo
    ) {}

    /**
     * Construction du formulaire avec tous les champs d'une maintenance.
     *
     * @param FormBuilderInterface  $builder  Constructeur de formulaire Symfony
     * @param array<string, mixed>  $options  Options du formulaire (doit contenir 'user_id') // Fix PHPStan
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $userId = $options['user_id'];

        // ── Charger les équipements de l'agriculteur connecté ────────────────
        // Triés par nom alphabétique pour faciliter la sélection dans la liste
        $equipements = $this->equipementRepo->findBy(
            ['userlog' => $userId],
            ['nom' => 'ASC']
        );

        // ── Construire les choices : "Nom — Type" => id ───────────────────────
        // Le label "Nom — Type" aide l'utilisateur à identifier son équipement
        // sans avoir à se souvenir des IDs numériques
        $equipementChoices = [];
        foreach ($equipements as $eq) {
            $label = $eq->getNom() . ' — ' . $eq->getType();
            $equipementChoices[$label] = $eq->getId();
        }

        $builder
            // ── Équipement (FK entière via ChoiceType) ───────────────────────
            ->add('equipementId', ChoiceType::class, [
                'label'       => 'Équipement concerné',
                'choices'     => $equipementChoices,
                'placeholder' => '-- Choisir un équipement --',
                'required'    => false,
                'attr'        => ['class' => 'form-select'],
            ])

            // ── Type : Préventive (planifiée) ou Corrective (réaction à une panne) ──
            ->add('type', ChoiceType::class, [
                'label'       => 'Type de maintenance',
                'choices'     => Maintenance::TYPES,
                'placeholder' => '-- Choisir un type --',
                'required'    => false,
                'attr'        => ['class' => 'form-select'],
            ])

            // ── Catégorie : Régulière (périodique) ou Imprévue (urgence) ─────
            ->add('categorie', ChoiceType::class, [
                'label'       => 'Catégorie',
                'choices'     => Maintenance::CATEGORIES,
                'placeholder' => '-- Choisir une catégorie --',
                'required'    => false,
                'attr'        => ['class' => 'form-select'],
            ])

            // ── Statut : cycle de vie (Planifiée → En cours → Terminée/Annulée) ──
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => Maintenance::STATUTS,
                'required' => false,
                'attr'    => ['class' => 'form-select'],
            ])

            // ── Description : min 10 chars (contrainte dans l'entité) ────────
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'placeholder' => 'Décrivez les travaux à effectuer... (min. 10 caractères)',
                    'class'       => 'form-control',
                ],
            ])

            // ── Date planifiée : doit être aujourd'hui ou dans le futur ──────
            ->add('datePlanifiee', DateType::class, [
                'label'    => 'Date planifiée',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])

            // ── Date réelle : optionnelle, renseignée après la maintenance ────
            ->add('dateReelle', DateType::class, [
                'label'    => 'Date réelle (optionnelle)',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])

            // ── Technicien : optionnel, lettres/espaces/tirets uniquement ────
            ->add('technicien', TextType::class, [
                'label'    => 'Technicien',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'placeholder' => 'Nom du technicien',
                ],
            ])

            // ── Coût en DT (Dinars Tunisiens) ─────────────────────────────────
            // html5=false : évite le comportement variable des <input type="number">
            // selon la locale du navigateur (certains refusent la virgule comme séparateur)
            ->add('cout', NumberType::class, [
                'label'    => 'Coût (DT)',
                'required' => false,
                'scale'    => 2,    // deux décimales
                'html5'    => false, // rendu en <input type="text"> pour compatibilité locale
                'attr'     => [
                    'class'       => 'form-control',
                    'placeholder' => '0.00',
                ],
            ]);
    }

    /**
     * Configure les options du formulaire.
     *
     * Options personnalisées :
     *  - 'user_id' (int, obligatoire) : ID de l'utilisateur connecté pour filtrer
     *    les équipements dans le champ de sélection. Déclaré comme 'required'
     *    pour lever une exception claire si le contrôleur l'oublie.
     *
     * @param OptionsResolver $resolver  Résolveur d'options Symfony
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Maintenance::class,
            'user_id'    => null,
        ]);

        // user_id est obligatoire — le contrôleur doit toujours le passer
        $resolver->setRequired('user_id');
    }
}
