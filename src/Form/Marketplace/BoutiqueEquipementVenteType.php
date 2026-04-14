<?php

namespace App\Form\Marketplace;

use App\Dto\Marketplace\BoutiqueEquipementVenteDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class BoutiqueEquipementVenteType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'eq_vente';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('condition', ChoiceType::class, [
                'label' => 'Condition',
                'choices' => [
                    'Neuf' => 'NEUF',
                    'Occasion' => 'OCCASION',
                ],
                'expanded' => true,
                'required' => false,
                'constraints' => [
                    new Choice([
                        'choices' => ['NEUF', 'OCCASION'],
                        'message' => 'La condition doit être Neuf ou Occasion.',
                    ]),
                ],
            ])
            ->add('used_value', TextType::class, [
                'label' => "Durée d'utilisation (valeur)",
                'required' => false,
                'attr' => ['placeholder' => 'Ex : 6'],
            ])
            ->add('used_unit', ChoiceType::class, [
                'label' => 'Unité',
                'choices' => [
                    'Mois' => 'mois',
                    'Ans' => 'ans',
                ],
                'required' => false,
                'constraints' => [
                    new Choice(['choices' => ['mois', 'ans'], 'message' => 'Unité invalide.']),
                ],
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix (DT)',
                'html5' => false,
                'scale' => 3,
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir un prix.']),
                    new Type(['type' => 'numeric', 'message' => 'Le prix doit être un nombre.']),
                    new GreaterThan([
                        'value' => 0,
                        'message' => 'Le prix doit être supérieur à 0.',
                    ]),
                ],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité en stock',
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir la quantité en stock.']),
                    new Type(['type' => 'integer', 'message' => 'La quantité doit être un nombre entier.']),
                    new GreaterThan([
                        'value' => 0,
                        'message' => 'La quantité doit être au moins 1.',
                    ]),
                ],
            ])
            ->add('warranty_enabled', ChoiceType::class, [
                'label' => 'Garantie',
                'choices' => [
                    'Sans garantie' => '0',
                    'Avec garantie' => '1',
                ],
                'expanded' => true,
                'required' => false,
                'constraints' => [
                    new Choice(['choices' => ['0', '1'], 'message' => 'Choix de garantie invalide.']),
                ],
            ])
            ->add('warranty_duration', TextType::class, [
                'label' => 'Durée de garantie',
                'required' => false,
                'attr' => ['placeholder' => 'Ex : 6 mois / 1 an'],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!$data instanceof BoutiqueEquipementVenteDto) {
                return;
            }
            $form = $event->getForm();

            if ($data->warranty_enabled === '1' && trim((string) $data->warranty_duration) === '') {
                $form->get('warranty_duration')->addError(
                    new FormError('Veuillez préciser la durée de garantie.')
                );
            }

            if ($data->condition === 'OCCASION') {
                $raw = trim((string) ($data->used_value ?? ''));
                if ($raw === '' || !ctype_digit($raw) || (int) $raw < 0) {
                    $form->get('used_value')->addError(
                        new FormError('Veuillez indiquer la durée d’utilisation (occasion), en nombre entier positif ou zéro.')
                    );
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BoutiqueEquipementVenteDto::class,
            'csrf_protection' => false,
        ]);
    }
}
