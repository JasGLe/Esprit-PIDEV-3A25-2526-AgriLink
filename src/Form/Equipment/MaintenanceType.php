<?php

namespace App\Form\Equipment;

use App\Entity\Maintenance;
use App\Repository\EquipementRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaintenanceType extends AbstractType
{
    public function __construct(
        private EquipementRepository $equipementRepo
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $userId = $options['user_id'];

        // Récupérer les équipements de l'agriculteur pour la liste déroulante
        $equipements = $this->equipementRepo->findBy(
            ['userlog' => $userId],
            ['nom' => 'ASC']
        );

        // Construire les choices : "Nom (Type)" => id
        $equipementChoices = [];
        foreach ($equipements as $eq) {
            $label = $eq->getNom() . ' — ' . $eq->getType();
            $equipementChoices[$label] = $eq->getId();
        }

        $builder
            ->add('equipementId', ChoiceType::class, [
                'label'       => 'Équipement concerné',
                'choices'     => $equipementChoices,
                'placeholder' => '-- Choisir un équipement --',
                'required'    => false,
                'attr'        => ['class' => 'form-select'],
            ])

            ->add('type', ChoiceType::class, [
                'label'       => 'Type de maintenance',
                'choices'     => Maintenance::TYPES,
                'placeholder' => '-- Choisir un type --',
                'required'    => false,
                'attr'        => ['class' => 'form-select'],
            ])

            ->add('categorie', ChoiceType::class, [
                'label'       => 'Catégorie',
                'choices'     => Maintenance::CATEGORIES,
                'placeholder' => '-- Choisir une catégorie --',
                'required'    => false,
                'attr'        => ['class' => 'form-select'],
            ])

            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => Maintenance::STATUTS,
                'required' => false,
                'attr'    => ['class' => 'form-select'],
            ])

            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'placeholder' => 'Décrivez les travaux à effectuer... (min. 10 caractères)',
                    'class'       => 'form-control',
                ],
            ])

            ->add('datePlanifiee', DateType::class, [
                'label'    => 'Date planifiée',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])

            ->add('dateReelle', DateType::class, [
                'label'    => 'Date réelle (optionnelle)',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Maintenance::class,
            'user_id'    => null,
        ]);

        $resolver->setRequired('user_id');
    }
}