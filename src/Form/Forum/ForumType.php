<?php

namespace App\Form\Forum;

use App\Entity\Forum\Forum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumType extends AbstractType
{
    private const CATEGORY_CHOICES = [
        'Agriculture generale' => 'Agriculture generale',
        'Elevage' => 'Elevage',
        'Cultures' => 'Cultures',
        'Equipements' => 'Equipements',
        'Meteo' => 'Meteo',
        'Autre' => 'Autre',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'trim' => true,
                'empty_data' => null,
                'required' => true,
                'attr' => [
                    'minlength' => 2,
                    'maxlength' => 150,
                    'placeholder' => 'Titre du sujet',
                ],
            ])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Categorie',
                'choices' => self::CATEGORY_CHOICES,
                'placeholder' => 'Choisir une categorie',
                'empty_data' => null,
                'required' => true,
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
                'empty_data' => null,
                'required' => true,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Forum::class]);
    }
}
