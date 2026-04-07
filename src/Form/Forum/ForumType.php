<?php

namespace App\Form\Forum;

use App\Entity\Forum\Forum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumType extends AbstractType
{
    private const CATEGORY_CHOICES = [
        'Agriculture generale' => 'Agriculture générale',
        'Elevage' => 'Élevage',
        'Cultures' => 'Cultures',
        'Equipements' => 'Équipements',
        'Meteo' => 'Météo',
        'Autre' => 'Autre',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'trim' => true,
                'empty_data' => '',
            ])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Categorie',
                'choices' => self::CATEGORY_CHOICES,
                'placeholder' => 'Choisir une categorie',
                'empty_data' => '',
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $forum = $event->getData();

            if (!$forum instanceof Forum) {
                return;
            }

            $currentCategory = $forum->getCategorie();
            if ($currentCategory === '' || \in_array($currentCategory, self::CATEGORY_CHOICES, true)) {
                return;
            }

            $choices = self::CATEGORY_CHOICES;
            $choices['Categorie actuelle'] = $currentCategory;

            $event->getForm()->add('categorie', ChoiceType::class, [
                'label' => 'Categorie',
                'choices' => $choices,
                'placeholder' => 'Choisir une categorie',
                'empty_data' => '',
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Forum::class]);
    }
}
