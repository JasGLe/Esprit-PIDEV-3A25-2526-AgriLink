<?php

namespace App\Form\Forum;

use App\Dto\Forum\YieldForecastData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class YieldForecastType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('surface', TextType::class, [
                'label' => 'Surface',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex : 12.5',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('cropCoefficient', TextType::class, [
                'label' => 'Coefficient culture',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex : 1.30',
                    'inputmode' => 'decimal',
                ],
            ])
            ->add('weatherCoefficient', TextType::class, [
                'label' => 'Coefficient meteo',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex : 0.85',
                    'inputmode' => 'decimal',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => YieldForecastData::class,
        ]);
    }
}
