<?php

namespace App\Form\UserManagement;

use App\Entity\UserManagement\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
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
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationAgriPlusType extends AbstractType
{
    private const GOUVERNORATS = [
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
        $inputClass = 'mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm';
        $labelClass = 'block text-sm font-medium text-gray-700';

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom complet',
                'attr' => [
                    'placeholder' => 'Votre nom complet',
                    'class' => $inputClass,
                ],
                'label_attr' => ['class' => $labelClass],
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'placeholder' => 'votre@email.com',
                    'class' => $inputClass,
                ],
                'label_attr' => ['class' => $labelClass],
                'constraints' => [
                    new NotBlank(message: "L'adresse email est obligatoire."),
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
                        'class' => $inputClass,
                    ],
                    'label_attr' => ['class' => $labelClass],
                    'constraints' => [
                        new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                        new Length(min: 6, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.', max: 4096,),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'placeholder' => '••••••••',
                        'class' => $inputClass,
                    ],
                    'label_attr' => ['class' => $labelClass],
                ],
                'mapped' => false,
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '55123456',
                    'class' => $inputClass,
                    'inputmode' => 'numeric',
                    'maxlength' => 15,
                ],
                'label_attr' => ['class' => $labelClass],
                'constraints' => [
                    new Regex(pattern: '/^\d{8,15}$/', message: 'Le numéro de téléphone doit contenir entre 8 et 15 chiffres.'),
                ],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => $inputClass,
                ],
                'label_attr' => ['class' => $labelClass],
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
                'placeholder' => 'Sélectionnez votre gouvernorat',
                'choices' => self::GOUVERNORATS,
                'attr' => [
                    'class' => $inputClass,
                ],
                'label_attr' => ['class' => $labelClass],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Votre ville',
                    'class' => $inputClass,
                ],
                'label_attr' => ['class' => $labelClass],
            ])
            ->add('codePostale', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 1000',
                    'class' => $inputClass,
                    'maxlength' => 4,
                ],
                'label_attr' => ['class' => $labelClass],
                'constraints' => [
                    new Regex(pattern: '/^\d{4}$/', message: 'Le code postal doit contenir exactement 4 chiffres.'),
                ],
            ])
            ->add('agriplusAbonnement', ChoiceType::class, [
                'label' => 'Plan d\'abonnement',
                'required' => true,
                'expanded' => true,
                'data' => 'PREMIUM',
                'choices' => [
                    'Basic - 29 DT/mois' => 'BASIC',
                    'Premium - 59 DT/mois' => 'PREMIUM',
                    'Enterprise - 149 DT/mois' => 'ENTERPRISE',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner un plan d\'abonnement.'),
                ],
                'attr' => [
                    'class' => 'subscription-plans',
                ],
                'label_attr' => ['class' => $labelClass],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J\'accepte les conditions d\'utilisation et la politique de confidentialité',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter les conditions d\'utilisation.'),
                ],
                'attr' => ['class' => 'h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded'],
                'label_attr' => ['class' => 'ml-2 block text-sm text-gray-900'],
            ])
            // Note: Submit button is rendered manually in the template
            // ->add('submit', SubmitType::class, [...])
        ;
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
