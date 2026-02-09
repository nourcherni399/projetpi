<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Blog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
<<<<<<< HEAD
use Symfony\Component\Validator\Constraints\Length;
=======
>>>>>>> bc1944e (Integration user - PI)
use Symfony\Component\Validator\Constraints\NotBlank;

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
<<<<<<< HEAD
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.']),
                ],
=======
                'constraints' => [new NotBlank(message: 'Le titre est obligatoire.')],
>>>>>>> bc1944e (Integration user - PI)
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
<<<<<<< HEAD
                'constraints' => [
                    new NotBlank(message: 'Le contenu est obligatoire.'),
                    new Length(['min' => 1, 'max' => 65535, 'maxMessage' => 'Le contenu ne peut pas dépasser {{ limit }} caractères.']),
                ],
=======
                'constraints' => [new NotBlank(message: 'Le contenu est obligatoire.')],
>>>>>>> bc1944e (Integration user - PI)
                'attr' => $attr + ['rows' => 8, 'placeholder' => 'Contenu de l\'article...'],
            ])
            ->add('image', TextType::class, [
                'label' => 'Image (URL ou chemin)',
                'required' => false,
                'empty_data' => '',
<<<<<<< HEAD
                'constraints' => [new Length(['max' => 500, 'maxMessage' => 'L\'URL ne peut pas dépasser {{ limit }} caractères.'])],
=======
>>>>>>> bc1944e (Integration user - PI)
                'attr' => $attr + ['placeholder' => 'https://... ou chemin vers l\'image'],
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
