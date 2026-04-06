<?php

namespace App\Form\Marketplace;

use App\Entity\Marketplace\Produits;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class BoutiqueCultureProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $nomOpts = [
            'label' => 'Nom du produit',
            'constraints' => [new NotBlank(['message' => 'Le nom est obligatoire.'])],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Ex. Tomates cerises bio',
                'autocomplete' => 'off',
            ],
        ];
        if ($options['lock_nom']) {
            $nomOpts['attr']['readonly'] = 'readonly';
            $nomOpts['help'] = 'Lié à la culture : le nom ne peut pas être modifié.';
        }

        $builder
            ->add('nom', TextType::class, $nomOpts)
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie (culture)',
                'choices' => [
                    'Légume' => 'LEGUME',
                    'Fruit' => 'FRUIT',
                    'Grains' => 'GRAINS',
                ],
                'placeholder' => 'Choisir…',
                'constraints' => [new NotBlank(['message' => 'Choisissez une catégorie.'])],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('uniteVente', ChoiceType::class, [
                'label' => 'Unité de vente',
                'mapped' => false,
                'choices' => [
                    'Kilogramme (kg)' => 'kg',
                    'Tonne' => 'tonne',
                    'Quintal' => 'quintal',
                    'Litre' => 'litre',
                    'Pièce' => 'piece',
                ],
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('prixUnitaire', NumberType::class, [
                'label' => 'Prix par unité (DT)',
                'html5' => true,
                'scale' => 3,
                'constraints' => [new NotBlank(), new Positive(message: 'Le prix doit être positif.')],
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.001',
                    'min' => 0,
                ],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité en stock',
                'constraints' => [new NotBlank(), new Positive(message: 'La quantité doit être au moins 1.')],
                'attr' => ['class' => 'form-control', 'min' => 1],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Origine, qualité, disponibilité…',
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $produit = $event->getData();
            if (!$produit instanceof Produits || !$produit->getCategorie()) {
                return;
            }
            if (preg_match('/ · (.+)$/', $produit->getCategorie(), $m)) {
                $event->getForm()->get('uniteVente')->setData(trim($m[1]));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produits::class,
            'lock_nom' => false,
        ]);
        $resolver->setAllowedTypes('lock_nom', 'bool');
    }
}
