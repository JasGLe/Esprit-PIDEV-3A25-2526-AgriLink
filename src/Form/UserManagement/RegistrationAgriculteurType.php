<?php

namespace App\Form\UserManagement;

use App\Entity\UserManagement\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationAgriculteurType extends AbstractType
{
    private const INPUT_CLASS = 'mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm';
    private const LABEL_CLASS = 'block text-sm font-medium text-gray-700';

    private const GOVERNORATES = [
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
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Personal Information
            ->add('nom', TextType::class, [
                'label' => 'Nom complet',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Votre nom complet',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'required' => true,
                'attr' => [
                    'placeholder' => 'votre@email.com',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new NotBlank(message: "L'email est obligatoire."),
                    new Email(message: "L'adresse email '{{ value }}' n'est pas valide."),
                    new Length(max: 150, maxMessage: "L'email ne peut pas dépasser {{ limit }} caractères."),
                ],
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
                        'autocomplete' => 'new-password',
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
                        'autocomplete' => 'new-password',
                    ],
                    'label_attr' => ['class' => self::LABEL_CLASS],
                ],
                'mapped' => false,
            ])
            // Contact Information
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '55123456',
                    'class' => self::INPUT_CLASS,
                    'inputmode' => 'numeric',
                    'maxlength' => 15,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new Regex(pattern: '/^\d{8,15}$/', message: 'Le numéro de téléphone doit contenir entre 8 et 15 chiffres.'),
                ],
            ])
            // Location Information
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new LessThanOrEqual(
                        value: new \DateTimeImmutable('-18 years'),
                        message: 'Vous devez avoir au moins 18 ans.'
                    ),
                ],
            ])
            ->add('gouvernant', ChoiceType::class, [
                'label' => 'Gouvernorat',
                'required' => false,
                'choices' => self::GOVERNORATES,
                'placeholder' => 'Sélectionnez votre gouvernorat',
                'attr' => [
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Votre ville',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
            ])
            ->add('codePostale', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => [
                    'placeholder' => '1000',
                    'class' => self::INPUT_CLASS,
                    'maxlength' => 4,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new Regex(pattern: '/^\d{4}$/', message: 'Le code postal doit contenir exactement 4 chiffres.'),
                ],
            ])
            // Professional Information
            ->add('agriculteurExpAnnee', IntegerType::class, [
                'label' => 'Années d\'expérience en agriculture',
                'required' => true,
                'attr' => [
                    'placeholder' => '0',
                    'class' => self::INPUT_CLASS,
                    'min' => 0,
                    'max' => 60,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new NotBlank(message: "L'expérience est obligatoire."),
                    new Range(min: 0, max: 60, notInRangeMessage: "L'expérience doit être entre {{ min }} et {{ max }} années."),
                ],
            ])
            // Terms
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J\'accepte les conditions d\'utilisation',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter les conditions d\'utilisation.'),
                ],
                'attr' => ['class' => 'h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded'],
                'label_attr' => ['class' => 'ml-2 block text-sm text-gray-900'],
            ]);
            // ->add('submit', SubmitType::class, [
            //     'label' => 'Créer mon compte Agriculteur',
            //     'attr' => [
            //         'class' => 'group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500',
            //     ],
            // ]);
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
