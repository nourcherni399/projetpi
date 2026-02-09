<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Produit;
use App\Enum\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
<<<<<<< HEAD
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
=======
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du produit',
<<<<<<< HEAD
=======
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'placeholder' => 'Ex. Coussin sensoriel lesté',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
<<<<<<< HEAD
=======
                'required' => false,
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'rows' => 4,
                    'placeholder' => 'Description du produit…',
                ],
            ])
            ->add('categorie', EnumType::class, [
                'label' => 'Catégorie',
                'class' => Categorie::class,
                'choice_label' => fn (Categorie $c) => $c->label(),
                'placeholder' => 'Choisir une catégorie',
<<<<<<< HEAD
=======
                'constraints' => [new NotBlank(message: 'La catégorie est obligatoire.')],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                ],
            ])
            ->add('prix', NumberType::class, [
<<<<<<< HEAD
                'label' => 'Prix (د.ت)',
                'scale' => 2,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
=======
                'label' => 'Prix (€)',
                'html5' => true,
                'scale' => 2,
                'constraints' => [
                    new NotBlank(message: 'Le prix est obligatoire.'),
                    new PositiveOrZero(message: 'Le prix doit être positif ou nul.'),
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'min' => 0,
                    'step' => '0.01',
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                    'placeholder' => '0.00',
                ],
            ])
            ->add('disponibilite', CheckboxType::class, [
                'label' => 'Disponible',
                'required' => false,
                'data' => true,
                'attr' => [
                    'class' => 'rounded border-[#E5E0D8] text-[#A7C7E7] focus:ring-[#A7C7E7]',
                ],
                'label_attr' => ['class' => 'text-[#4B5563]'],
            ])
<<<<<<< HEAD
            ->add('image', FileType::class, [
                'label' => 'Image du produit',
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
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF ou WebP).',
                        'maxSizeMessage' => 'L\'image ne doit pas dépasser 5MB.',
                        'uploadErrorMessage' => 'Une erreur est survenue lors de l\'upload de l\'image.',
                    ])
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'accept' => 'image/*',
=======
            ->add('image', UrlType::class, [
                'label' => 'URL de l\'image',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'placeholder' => 'https://…',
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
