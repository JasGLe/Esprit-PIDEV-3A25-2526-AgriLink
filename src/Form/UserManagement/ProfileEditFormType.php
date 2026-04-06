<?php

namespace App\Form\UserManagement;

use App\Entity\UserManagement\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

class ProfileEditFormType extends AbstractType
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
            // Photo de profil
            ->add('profilePhoto', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/jpg,image/png,image/webp',
                    'class' => 'mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100'
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'help' => 'Formats acceptés : JPG, PNG, WEBP (max 2 Mo)',
                'constraints' => [
                    new Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Veuillez télécharger une image valide (JPG, PNG ou WEBP).',
                        maxSizeMessage: 'Le fichier est trop volumineux ({{ size }} {{ suffix }}). Taille maximale : {{ limit }} {{ suffix }}.',
                    ),
                ],
            ])
            // Informations personnelles
            ->add('nom', TextType::class, [
                'label' => 'Nom complet',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Votre nom complet',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => '+216 XX XXX XXX',
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
                'constraints' => [
                    new Regex(
                        pattern: '/^[0-9\s\+\-\(\)]+$/',
                        message: "Le numéro de téléphone n'est pas valide."
                    ),
                ],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => self::INPUT_CLASS,
                ],
                'label_attr' => ['class' => self::LABEL_CLASS],
            ])
            // Localisation
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
            ]);

        // Champs spécifiques selon le rôle
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $user = $event->getData();
            $form = $event->getForm();

            if (!$user instanceof User) {
                return;
            }

            // Champs spécifiques Agriculteur
            if ($user->isAgriculteur() || $user->isAgriPlus()) {
                $form->add('agriculteurExpAnnee', IntegerType::class, [
                    'label' => 'Années d\'expérience en agriculture',
                    'required' => false,
                    'attr' => [
                        'placeholder' => '0',
                        'class' => self::INPUT_CLASS,
                        'min' => 0,
                        'max' => 60,
                    ],
                    'label_attr' => ['class' => self::LABEL_CLASS],
                    'constraints' => [
                        new Range(
                            min: 0,
                            max: 60,
                            notInRangeMessage: "L'expérience doit être entre {{ min }} et {{ max }} années."
                        ),
                    ],
                ]);
            }

            // Champs spécifiques Fournisseur (certifications modifiables)
            if ($user->isFournisseur()) {
                $form->add('fournisseurCertifications', TextareaType::class, [
                    'label' => 'Certifications',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Liste de vos certifications (Bio, ISO, etc.)',
                        'class' => self::INPUT_CLASS . ' h-24',
                        'rows' => 3,
                    ],
                    'label_attr' => ['class' => self::LABEL_CLASS],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}