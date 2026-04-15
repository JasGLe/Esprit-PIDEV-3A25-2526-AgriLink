<?php

namespace App\Form\Forum;

use App\Dto\Forum\CropRecommendationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CropRecommendationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('soil', ChoiceType::class, [
                'label' => 'Type de sol',
                'placeholder' => 'Choisir un sol',
                'choices' => [
                    'Sableux' => 'sableux',
                    'Argileux' => 'argileux',
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('season', ChoiceType::class, [
                'label' => 'Saison',
                'placeholder' => 'Choisir une saison',
                'choices' => [
                    'Ete' => 'ete',
                    'Hiver' => 'hiver',
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-select',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CropRecommendationData::class,
        ]);
    }
}
