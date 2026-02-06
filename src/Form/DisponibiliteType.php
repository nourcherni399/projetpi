<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Disponibilite;
use App\Enum\Jour;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class DisponibiliteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('jour', EnumType::class, [
                'label' => 'Jour',
                'class' => Jour::class,
                'choice_label' => fn (Jour $j) => ucfirst($j->value),
                'placeholder' => 'Choisir un jour',
                'constraints' => [new NotBlank(message: 'Le jour est obligatoire.')],
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
                'required' => false,
                'data' => 30,
                'constraints' => [new PositiveOrZero()],
                'attr' => $attr + ['placeholder' => '30', 'min' => 0],
            ])
            ->add('estDispo', CheckboxType::class, [
                'label' => 'Disponible',
                'required' => false,
                'data' => true,
                'attr' => ['class' => 'rounded border-[#E5E0D8] text-[#A7C7E7] focus:ring-[#A7C7E7]'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => [
                    'class' => 'w-full inline-flex justify-center items-center px-6 py-3 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-[#A7C7E7] hover:bg-[#8BB4D9] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#A7C7E7] transition-colors duration-200',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Disponibilite::class,
        ]);
    }
}