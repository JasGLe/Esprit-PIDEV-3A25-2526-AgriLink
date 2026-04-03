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
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

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
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'placeholder' => 'votre@email.com',
                    'class' => $inputClass,
                ],
                'label_attr' => ['class' => $labelClass],
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
                    'placeholder' => '+216 XX XXX XXX',
                    'class' => $inputClass,
                ],
                'label_attr' => ['class' => $labelClass],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => $inputClass,
                ],
                'label_attr' => ['class' => $labelClass],
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
                ],
                'label_attr' => ['class' => $labelClass],
            ])
            ->add('agriplusAbonnement', ChoiceType::class, [
                'label' => 'Plan d\'abonnement',
                'required' => true,
                'expanded' => true,
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
            ->add('submit', SubmitType::class, [
                'label' => 'Créer mon compte AgriPlus',
                'attr' => [
                    'class' => 'group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500',
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
