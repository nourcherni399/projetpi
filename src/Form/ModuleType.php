<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Module;
use App\Enum\CategorieModule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ModuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
            'novalidate' => 'novalidate',
        ];

        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire.'),
                    new Length(['min' => 3, 'max' => 255, 'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.', 'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.'])
                ],
                'attr' => $attr + ['placeholder' => 'Ex. Introduction au TSA'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'constraints' => [
                    new NotBlank(message: 'La description est obligatoire.'),
                    new Length(['min' => 10, 'max' => 255, 'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.', 'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'])
                ],
                'attr' => $attr + ['rows' => 3, 'placeholder' => 'Courte description du module…'],
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu',
                'constraints' => [
                    new NotBlank(message: 'Le contenu est obligatoire.'),
                    new Length(['min' => 20, 'minMessage' => 'Le contenu doit contenir au moins {{ limit }} caractères.'])
                ],
                'attr' => $attr + ['rows' => 5, 'placeholder' => 'Contenu détaillé du module…'],
            ])
            ->add('niveau', ChoiceType::class, [
                'label' => 'Niveau',
                'choices' => [
                    'Facile' => 'facile',
                    'Moyen' => 'moyen',
                    'Difficile' => 'difficile',
                ],
                'constraints' => [new NotBlank(message: 'Le niveau est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('categorie', EnumType::class, [
                'label' => 'Catégorie',
                'class' => CategorieModule::class,
                'choice_label' => fn (CategorieModule $c) => $c->label() ?: 'Non défini',
                'placeholder' => 'Choisir une catégorie',
                'constraints' => [new NotBlank(message: 'La catégorie est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('image', FileType::class, [
                'label' => 'Image du module',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF ou WebP).',
                        'maxSizeMessage' => 'L\'image ne doit pas dépasser 5MB.',
                        'uploadErrorMessage' => 'Une erreur est survenue lors de l\'upload de l\'image.',
                    ]),
                ],
                'attr' => $attr + ['accept' => 'image/*'],
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Publié',
                'required' => false,
                'data' => false,
                'attr' => [
                    'class' => 'rounded border-[#E5E0D8] text-[#A7C7E7] focus:ring-[#A7C7E7]',
                ],
                'label_attr' => ['class' => 'text-[#4B5563]'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Module::class,
        ]);
    }
}
