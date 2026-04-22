<?php

namespace App\Form\Marketplace;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

class PromoCodeAssignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $builder
            ->add('code', TextType::class, [
                'label' => 'Code promo',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Le code promo est obligatoire.']),
                    new Length(['min' => 3, 'max' => 40]),
                    new Regex([
                        'pattern' => '/^[A-Za-z0-9_-]+$/',
                        'message' => 'Le code promo accepte uniquement lettres, chiffres, "_" et "-".',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: RAMADAN25',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('discount_percent', IntegerType::class, [
                'label' => 'Remise (%)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Le pourcentage de remise est obligatoire.']),
                    new Range([
                        'min' => 1,
                        'max' => 100,
                        'notInRangeMessage' => 'La remise doit être entre {{ min }}% et {{ max }}%.',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 15',
                    'inputmode' => 'numeric',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('start_date', DateType::class, [
                'label' => 'Date de début',
                'mapped' => false,
                'required' => false,
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(['message' => 'La date de début est obligatoire.']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'min' => $today,
                ],
            ])
            ->add('end_date', DateType::class, [
                'label' => 'Date de fin',
                'mapped' => false,
                'required' => false,
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(['message' => 'La date de fin est obligatoire.']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'min' => $today,
                ],
            ]);

        // Filled by JS from selected cards in "Ma Boutique"
        $builder->add('product_ids', HiddenType::class, [
            'mapped' => false,
            'required' => false,
            'constraints' => [
                new NotBlank(['message' => 'Sélectionnez au moins un produit.']),
                new Regex([
                    'pattern' => '/^\d+(,\d+)*$/',
                    'message' => 'Sélectionnez au moins un produit.',
                ]),
            ],
        ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            /** @var \DateTimeInterface|null $startDate */
            $startDate = $form->get('start_date')->getData();
            /** @var \DateTimeInterface|null $endDate */
            $endDate = $form->get('end_date')->getData();

            if ($startDate === null || $endDate === null) {
                return;
            }

            $today = new \DateTimeImmutable('today');
            $startDay = \DateTimeImmutable::createFromInterface($startDate)->setTime(0, 0, 0);
            $endDay = \DateTimeImmutable::createFromInterface($endDate)->setTime(0, 0, 0);

            if ($startDay < $today) {
                $form->get('start_date')->addError(new FormError('La date de début ne peut pas être dans le passé.'));
            }

            if ($endDay <= $startDay) {
                $form->get('end_date')->addError(new FormError('La date de fin doit être après la date de début.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}