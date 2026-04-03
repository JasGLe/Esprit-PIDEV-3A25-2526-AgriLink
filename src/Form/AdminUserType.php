<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Regex;

class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            // Basic Information
            ->add('nom', TextType::class, [
                'label' => 'Nom complet',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => 'Nom complet',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => 'email@example.com',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => !$isEdit,
                'first_options' => [
                    'label' => $isEdit ? 'Nouveau mot de passe (laisser vide pour conserver)' : 'Mot de passe',
                    'attr' => [
                        'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                        'placeholder' => $isEdit ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe',
                        'autocomplete' => 'new-password',
                    ],
                    'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                        'placeholder' => 'Confirmer le mot de passe',
                        'autocomplete' => 'new-password',
                    ],
                    'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => $isEdit ? [] : [
                    new NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => '+216 XX XXX XXX',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])

            // Location
            ->add('gouvernant', TextType::class, [
                'label' => 'Gouvernorat',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => 'Ex: Tunis, Sfax, Sousse...',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => 'Ville',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('codePostale', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => '1000',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])

            // Role & Status
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Utilisateur' => User::ROLE_USER,
                    'Agriculteur' => User::ROLE_AGRICULTEUR,
                    'AgriPlus (Premium)' => User::ROLE_AGRIPLUS,
                    'Fournisseur' => User::ROLE_FOURNISSEUR,
                    'Administrateur' => User::ROLE_ADMIN,
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
                'attr' => [
                    'class' => 'h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500',
                ],
                'label_attr' => ['class' => 'ml-2 block text-sm text-gray-900'],
            ])
            ->add('emailVerified', CheckboxType::class, [
                'label' => 'Email vérifié',
                'required' => false,
                'attr' => [
                    'class' => 'h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500',
                ],
                'label_attr' => ['class' => 'ml-2 block text-sm text-gray-900'],
            ])

            // Profile Photo
            ->add('profilePhoto', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Veuillez uploader une image valide (JPEG, PNG ou WebP).',
                        maxSizeMessage: 'La taille du fichier ne doit pas dépasser 2 Mo.',
                    )
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100',
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])

            // Admin-specific fields
            ->add('adminNiveau', ChoiceType::class, [
                'label' => 'Niveau administrateur',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'choices' => [
                    'Super Admin' => User::ADMIN_SUPER,
                    'Admin' => User::ADMIN_NORMAL,
                    'Modérateur' => User::ADMIN_MODERATOR,
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])

            // Agriculteur-specific fields
            ->add('agriculteurExpAnnee', IntegerType::class, [
                'label' => 'Années d\'expérience',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => '0',
                    'min' => 0,
                    'max' => 60,
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
                'constraints' => [
                    new PositiveOrZero(message: 'L\'expérience doit être un nombre positif.'),
                ],
            ])

            // AgriPlus-specific fields
            ->add('agriplusAbonnement', ChoiceType::class, [
                'label' => 'Type d\'abonnement',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'choices' => [
                    'Basic' => User::SUBSCRIPTION_BASIC,
                    'Premium' => User::SUBSCRIPTION_PREMIUM,
                    'Enterprise' => User::SUBSCRIPTION_ENTERPRISE,
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('agriplusDateExpiration', DateType::class, [
                'label' => 'Date d\'expiration de l\'abonnement',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])

            // Fournisseur-specific fields
            ->add('fournisseurTypeFournisseur', ChoiceType::class, [
                'label' => 'Type de fournisseur',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'choices' => [
                    'Personne physique' => User::FOURNISSEUR_PERSONNE,
                    'Société' => User::FOURNISSEUR_SOCIETE,
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('fournisseurCin', TextType::class, [
                'label' => 'CIN (Carte d\'identité nationale)',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => '12345678',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
                'constraints' => [
                    new Regex(
                        pattern: '/^[0-9]{8}$/',
                        message: 'Le CIN doit contenir exactement 8 chiffres.',
                        match: true,
                    ),
                ],
            ])
            ->add('fournisseurRaisonSocial', TextType::class, [
                'label' => 'Raison sociale',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => 'Nom de la société',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('fournisseurNumRegistre', TextType::class, [
                'label' => 'Numéro de registre du commerce',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => 'Ex: A12345678',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('fournisseurFormeJuridique', ChoiceType::class, [
                'label' => 'Forme juridique',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'choices' => [
                    'SARL' => 'SARL',
                    'SA' => 'SA',
                    'SUARL' => 'SUARL',
                    'SNC' => 'SNC',
                    'SCS' => 'SCS',
                    'Autre' => 'AUTRE',
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('fournisseurCapital', MoneyType::class, [
                'label' => 'Capital social',
                'required' => false,
                'currency' => 'TND',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => '0.00',
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('fournisseurCertifications', TextareaType::class, [
                'label' => 'Certifications',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm',
                    'placeholder' => 'Liste des certifications (une par ligne)',
                    'rows' => 3,
                ],
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => [
                    'class' => 'inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
