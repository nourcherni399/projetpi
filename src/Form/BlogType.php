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
                'constraints' => [new NotBlank(message: 'Le titre est obligatoire.')],
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
                'constraints' => [new NotBlank(message: 'Le contenu est obligatoire.')],
                'attr' => $attr + ['rows' => 8, 'placeholder' => 'Contenu de l\'article...'],
            ])
            ->add('image', TextType::class, [
                'label' => 'Image (URL ou chemin)',
                'required' => false,
                'empty_data' => '',
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
