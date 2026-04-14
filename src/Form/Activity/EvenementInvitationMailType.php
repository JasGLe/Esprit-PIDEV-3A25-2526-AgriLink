<?php

namespace App\Form\Activity;

use App\Dto\Activity\EvenementInvitationMailDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EvenementInvitationMailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: invite@example.com',
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Veuillez saisir une adresse e-mail.',
                    ]),
                    new Assert\Email([
                        'message' => 'Veuillez saisir une adresse e-mail valide.',
                    ]),
                ],
            ])
            ->add('pdfFile', FileType::class, [
                'label' => 'Invitation PDF',
                'required' => true,
                'mapped' => true,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'application/pdf,.pdf',
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Veuillez importer un fichier PDF.',
                    ]),
                    new Assert\File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Seuls les fichiers PDF sont autorisés.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EvenementInvitationMailDto::class,
            'csrf_protection' => true,
        ]);
    }
}