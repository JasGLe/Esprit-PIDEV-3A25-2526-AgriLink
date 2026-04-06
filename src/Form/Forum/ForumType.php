<?php

namespace App\Form\Forum;

use App\Entity\Forum\Forum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, ['label' => 'Titre'])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Agriculture générale' => 'Agriculture générale',
                    'Élevage'              => 'Élevage',
                    'Cultures'             => 'Cultures',
                    'Équipements'          => 'Équipements',
                    'Météo'                => 'Météo',
                    'Autre'                => 'Autre',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Forum::class]);
    }
}