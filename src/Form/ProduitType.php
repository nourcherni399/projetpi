<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Produit;
use App\Enum\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
<<<<<<< HEAD
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
=======
<<<<<<< HEAD
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Range;
=======
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3

final class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du produit',
<<<<<<< HEAD
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
=======
<<<<<<< HEAD
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.']),
                ],
=======
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'placeholder' => 'Ex. Coussin sensoriel lesté',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
<<<<<<< HEAD
=======
<<<<<<< HEAD
                'constraints' => [new Length(['max' => 65535, 'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'])],
=======
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
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
                'constraints' => [new NotBlank(message: 'La catégorie est obligatoire.')],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                ],
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix (€)',
<<<<<<< HEAD
=======
<<<<<<< HEAD
                'scale' => 2,
                'constraints' => [
                    new NotBlank(message: 'Le prix est obligatoire.'),
                    new Range(['min' => 0, 'max' => 99999.99, 'notInRangeMessage' => 'Le prix doit être entre {{ min }} et {{ max }}.']),
=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
                'html5' => true,
                'scale' => 2,
                'constraints' => [
                    new NotBlank(message: 'Le prix est obligatoire.'),
                    new PositiveOrZero(message: 'Le prix doit être positif ou nul.'),
<<<<<<< HEAD
=======
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'min' => 0,
                    'step' => '0.01',
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
            ->add('image', UrlType::class, [
                'label' => 'URL de l\'image',
                'required' => false,
<<<<<<< HEAD
=======
<<<<<<< HEAD
                'constraints' => [new Length(['max' => 500, 'maxMessage' => 'L\'URL ne peut pas dépasser {{ limit }} caractères.'])],
=======
>>>>>>> bc1944e (Integration user - PI)
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'placeholder' => 'https://…',
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
