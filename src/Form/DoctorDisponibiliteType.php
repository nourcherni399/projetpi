<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Disponibilite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class DoctorDisponibiliteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'La date est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('heureDebut', TimeType::class, [
                'label' => 'Heure de début',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: "L'heure de début est obligatoire.")],
                'attr' => $attr,
            ])
            ->add('heureFin', TimeType::class, [
                'label' => 'Heure de fin',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: "L'heure de fin est obligatoire.")],
                'attr' => $attr,
            ])
            ->add('duree', IntegerType::class, [
                'label' => 'Durée (minutes)',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'La durée est obligatoire.'),
                    new PositiveOrZero(message: 'La durée doit être positive ou nulle.'),
                ],
                'attr' => $attr + ['placeholder' => '30', 'min' => 0],
            ])
            ->add('estDispo', CheckboxType::class, [
                'label' => 'Disponible',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'rounded border-[#E5E0D8] text-[#A7C7E7] focus:ring-[#A7C7E7]'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Disponibilite::class,
        ]);
    }
}
