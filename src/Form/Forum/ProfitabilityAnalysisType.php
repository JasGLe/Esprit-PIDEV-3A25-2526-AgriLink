<?php

namespace App\Form\Forum;

use App\Dto\Forum\ProfitabilityAnalysisData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfitabilityAnalysisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fields = [
            'revenue' => ['Revenu', 'Ex : 5000'],
            'fertilizer' => ['Engrais', 'Ex : 800'],
            'water' => ['Eau', 'Ex : 300'],
            'labor' => ["Main d'oeuvre", 'Ex : 1200'],
            'seeds' => ['Semences', 'Ex : 450'],
        ];

        foreach ($fields as $name => [$label, $placeholder]) {
            $builder->add($name, TextType::class, [
                'label' => $label,
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $placeholder,
                    'inputmode' => 'decimal',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfitabilityAnalysisData::class,
        ]);
    }
}
