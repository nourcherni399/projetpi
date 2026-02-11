<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RendezVous;
use App\Enum\StatusRendezVous;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DoctorRendezVousEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('status', EnumType::class, [
                'label' => 'Statut',
                'class' => StatusRendezVous::class,
                'choice_label' => fn (StatusRendezVous $s) => match ($s) {
                    StatusRendezVous::EN_ATTENTE => 'En attente',
                    StatusRendezVous::CONFIRMER => 'Confirmé',
                    StatusRendezVous::ANNULER => 'Annulé',
                },
                'constraints' => [new NotBlank(message: 'Le statut est obligatoire.')],
                'attr' => $attr,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
