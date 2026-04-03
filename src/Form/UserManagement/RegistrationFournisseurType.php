<?php

namespace App\Form\UserManagement;

use App\Entity\UserManagement\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFournisseurType extends AbstractType
{
    private const INPUT_CLASS = 'mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm';
    private const LABEL_CLASS = 'block text-sm font-medium text-gray-700';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Common fields
            ->add('nom', TextType::class, [
                'label' => 'Nom complet / Raison sociale',
                'attr' => [
                    'placeholder' => 'Votre nom ou nom de l\'entreprise',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre nom.'),
                    new Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'placeholder' => 'votre@email.com',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'required' => true,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => [
                        'placeholder' => '••••••••',
                        'class' => self::INPUT_CLASS,
                    ],
                    'label_attr' => ['class' => self::LABEL_CLASS],
                    'constraints' => [
                        new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                        new Length(min: 6, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.', max: 4096,),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'placeholder' => '••••••••',
                        'class' => self::INPUT_CLASS,
                    ],
                    'label_attr' => ['class' => self::LABEL_CLASS],
                ],
                'mapped' => false,
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Numéro de téléphone',
                'attr' => [
                    'placeholder' => '+216 XX XXX XXX',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre numéro de téléphone.'),
                    new Regex(pattern: '/^[0-9\s\+\-\(\)]+$/', message: 'Le numéro de téléphone n\'est pas valide.'),
                ],
            ])
            ->add('gouvernant', ChoiceType::class, [
                'label' => 'Gouvernorat',
                'placeholder' => 'Sélectionnez votre gouvernorat',
                'choices' => [
                    'Ariana' => 'Ariana',
                    'Béja' => 'Béja',
                    'Ben Arous' => 'Ben Arous',
                    'Bizerte' => 'Bizerte',
                    'Gabès' => 'Gabès',
                    'Gafsa' => 'Gafsa',
                    'Jendouba' => 'Jendouba',
                    'Kairouan' => 'Kairouan',
                    'Kasserine' => 'Kasserine',
                    'Kébili' => 'Kébili',
                    'Le Kef' => 'Le Kef',
                    'Mahdia' => 'Mahdia',
                    'La Manouba' => 'La Manouba',
                    'Médenine' => 'Médenine',
                    'Monastir' => 'Monastir',
                    'Nabeul' => 'Nabeul',
                    'Sfax' => 'Sfax',
                    'Sidi Bouzid' => 'Sidi Bouzid',
                    'Siliana' => 'Siliana',
                    'Sousse' => 'Sousse',
                    'Tataouine' => 'Tataouine',
                    'Tozeur' => 'Tozeur',
                    'Tunis' => 'Tunis',
                    'Zaghouan' => 'Zaghouan',
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner votre gouvernorat.'),
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'attr' => [
                    'placeholder' => 'Votre ville',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre ville.'),
                ],
            ])
            ->add('codePostale', TextType::class, [
                'label' => 'Code postal',
                'attr' => [
                    'placeholder' => '1000',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre code postal.'),
                    new Regex(pattern: '/^\d{4}$/', message: 'Le code postal doit contenir 4 chiffres.'),
                ],
            ])
            ->add('fournisseurTypeFournisseur', ChoiceType::class, [
                'label' => 'Type de fournisseur',
                'choices' => [
                    'Personne physique' => User::FOURNISSEUR_PERSONNE,
                    'Société' => User::FOURNISSEUR_SOCIETE,
                ],
                'expanded' => false,
                'multiple' => false,
                'attr' => [
                    'class' => self::INPUT_CLASS,
                    'id' => 'fournisseur_type',
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner votre type de fournisseur.'),
                ],
            ])
            // PERSONNE-specific fields
            ->add('fournisseurCin', TextType::class, [
                'label' => 'CIN (Carte d\'Identité Nationale)',
                'required' => false,
                'attr' => [
                    'placeholder' => '12345678',
                    'class' => self::INPUT_CLASS,
                    'maxlength' => 8,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new Regex(pattern: '/^\d{8}$/', message: 'Le CIN doit contenir exactement 8 chiffres.'),
                ],
            ])
            // SOCIETE-specific fields
            ->add('fournisseurRaisonSocial', TextType::class, [
                'label' => 'Raison sociale',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom officiel de la société',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
            ])
            ->add('fournisseurNumRegistre', TextType::class, [
                'label' => 'Numéro de registre de commerce',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Numéro d\'immatriculation',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
            ])
            ->add('fournisseurFormeJuridique', ChoiceType::class, [
                'label' => 'Forme juridique',
                'required' => false,
                'placeholder' => 'Sélectionnez la forme juridique',
                'choices' => [
                    'SARL - Société à Responsabilité Limitée' => 'SARL',
                    'SA - Société Anonyme' => 'SA',
                    'SUARL - Société Unipersonnelle à Responsabilité Limitée' => 'SUARL',
                    'SNC - Société en Nom Collectif' => 'SNC',
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
            ])
            ->add('fournisseurCapital', NumberType::class, [
                'label' => 'Capital social (TND)',
                'required' => false,
                'attr' => [
                    'placeholder' => '10000',
                    'class' => self::INPUT_CLASS,
                    'min' => 0,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'html5' => true,
            ])
            // Optional fields
            ->add('fournisseurCertifications', TextareaType::class, [
                'label' => 'Certifications (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Liste de vos certifications (Bio, ISO, etc.)',
                    'class' => self::INPUT_CLASS . ' h-24',
                    'rows' => 3,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J\'accepte les conditions d\'utilisation',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter les conditions d\'utilisation.'),
                ],
                'attr' => ['class' => 'h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded'],
                'label_attr' => ['class' => 'ml-2 block text-sm text-gray-900'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Créer mon compte Fournisseur',
                'attr' => [
                    'class' => 'group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'registration',
        ]);
    }
}
