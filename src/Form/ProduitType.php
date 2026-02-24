<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Produit;
use App\Entity\Stock;
use App\Enum\Categorie;
use App\Enum\StatutPublication;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
final class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'placeholder' => 'Ex. Coussin sensoriel lesté',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
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
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                ],
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix (DT)',
                'html5' => true,
                'scale' => 2,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
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
            ->add('stock', EntityType::class, [
                'label' => 'Stock',
                'class' => Stock::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisir un stock',
                'constraints' => [new NotBlank(message: 'Le stock est obligatoire.')],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                ],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité en stock',
                'data' => 1,
                'constraints' => [
                    new NotBlank(message: 'La quantité est obligatoire.'),
                    new Positive(message: 'La quantité doit être au moins 1.'),
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'min' => 1,
                    'max' => 999999,
                    'placeholder' => '1',
                ],
            ]);
        if ($options['show_statut_publication'] ?? true) {
            $builder->add('statutPublication', EnumType::class, [
                'label' => 'Statut de publication',
                'class' => StatutPublication::class,
                'choice_label' => fn (StatutPublication $s) => $s->label(),
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                ],
            ]);
        }
        $builder->add('image', FileType::class, [
                'label' => 'Image principale',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'accept' => 'image/*',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
            'show_statut_publication' => true,
        ]);
    }
}