<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Stock;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

final class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité',
                'data' => 0,
                'constraints' => [
                    new NotBlank(message: 'La quantité est obligatoire.'),
                    new Range(['min' => 0, 'max' => 2147483647, 'notInRangeMessage' => 'La quantité doit être entre {{ min }} et {{ max }}.']),
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'min' => 0,
                    'placeholder' => '0',
                ],
            ])
            ->add('produit', EntityType::class, [
                'label' => 'Produit',
                'class' => \App\Entity\Produit::class,
                'choice_label' => 'nom',
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('p')
                    ->leftJoin('p.stock', 's')
                    ->where('s.id IS NULL')
                    ->orderBy('p.nom', 'ASC'),
                'placeholder' => 'Choisir un produit (sans stock existant)',
                'constraints' => [new NotBlank(message: 'Le produit est obligatoire.')],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
        ]);
    }
}
