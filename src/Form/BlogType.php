<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Blog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

final class BlogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire.'),
                    new Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'empty_data' => '',
                'attr' => $attr + ['placeholder' => 'Titre de l\'article'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Recommandation' => 'recommandation',
                    'Plainte' => 'plainte',
                    'Question' => 'question',
                    'Expérience' => 'experience',
                ],
                'placeholder' => 'Choisir un type',
                'constraints' => [new NotBlank(message: 'Le type est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu',
                'constraints' => [
                    new NotBlank(message: 'Le contenu est obligatoire.'),
                    new Length([
                        'min' => 20,
                        'minMessage' => 'Le contenu doit contenir au moins {{ limit }} caractères.',
                    ]),
                ],
                'empty_data' => '',
                'attr' => $attr + ['rows' => 8, 'placeholder' => 'Contenu de l\'article...'],
            ])
            ->add('image', FileType::class, [
                'label' => 'Image de l\'article',
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
                'label' => 'Publier l\'article',
                'required' => false,
                'data' => false,
                'attr' => ['class' => 'rounded border-[#E5E0D8] text-[#A7C7E7] focus:ring-[#A7C7E7]'],
            ])
            ->add('isUrgent', CheckboxType::class, [
                'label' => 'Urgent',
                'required' => false,
                'data' => false,
                'attr' => ['class' => 'rounded border-[#E5E0D8] text-[#A7C7E7] focus:ring-[#A7C7E7]'],
            ])
            ->add('isVisible', CheckboxType::class, [
                'label' => 'Visible',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'rounded border-[#E5E0D8] text-[#A7C7E7] focus:ring-[#A7C7E7]'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Blog::class,
        ]);
    }
}
