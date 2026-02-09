<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Module;
<<<<<<< HEAD
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
=======
<<<<<<< HEAD
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
=======
use App\Enum\CategorieModule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
<<<<<<< HEAD
use Symfony\Component\Validator\Constraints\NotBlank;
=======
<<<<<<< HEAD
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
=======
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Url;
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3

final class ModuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
            'novalidate' => 'novalidate',
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        ];

        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
<<<<<<< HEAD
                'constraints' => [new NotBlank(message: 'Le titre est obligatoire.')],
=======
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire.'),
<<<<<<< HEAD
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.']),
=======
                    new Length(['min' => 3, 'max' => 255, 'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.', 'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.'])
>>>>>>> bc1944e (Integration user - PI)
                ],
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
                'attr' => $attr + ['placeholder' => 'Ex. Introduction au TSA'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
<<<<<<< HEAD
                'constraints' => [new NotBlank(message: 'La description est obligatoire.')],
=======
                'constraints' => [
                    new NotBlank(message: 'La description est obligatoire.'),
<<<<<<< HEAD
                    new Length(['min' => 1, 'max' => 65535, 'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.']),
=======
                    new Length(['min' => 10, 'max' => 255, 'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.', 'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'])
>>>>>>> bc1944e (Integration user - PI)
                ],
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
                'attr' => $attr + ['rows' => 3, 'placeholder' => 'Courte description du module…'],
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu',
<<<<<<< HEAD
                'constraints' => [new NotBlank(message: 'Le contenu est obligatoire.')],
=======
                'constraints' => [
                    new NotBlank(message: 'Le contenu est obligatoire.'),
<<<<<<< HEAD
                    new Length(['min' => 1, 'max' => 65535, 'maxMessage' => 'Le contenu ne peut pas dépasser {{ limit }} caractères.']),
=======
                    new Length(['min' => 20, 'minMessage' => 'Le contenu doit contenir au moins {{ limit }} caractères.'])
>>>>>>> bc1944e (Integration user - PI)
                ],
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
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
<<<<<<< HEAD
=======
<<<<<<< HEAD
=======
            ->add('categorie', EnumType::class, [
                'label' => 'Catégorie',
                'class' => CategorieModule::class,
                'choice_label' => fn (CategorieModule $c) => $c->label() ?: 'Non défini',
                'placeholder' => 'Choisir une catégorie',
                'constraints' => [new NotBlank(message: 'La catégorie est obligatoire.')],
                'attr' => $attr,
            ])
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
            ->add('image', TextType::class, [
                'label' => 'URL de l\'image',
                'required' => false,
                'empty_data' => '',
<<<<<<< HEAD
=======
<<<<<<< HEAD
                'constraints' => [new Length(['max' => 500, 'maxMessage' => 'L\'URL ne peut pas dépasser {{ limit }} caractères.'])],
=======
                'constraints' => [
                    new Url(message: 'Veuillez entrer une URL valide.')
                ],
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
                'attr' => $attr + ['placeholder' => 'https://…'],
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
