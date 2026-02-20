<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MessageEvenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class MessageEvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
            'rows' => 3,
        ];
        $builder->add('contenu', TextareaType::class, [
            'label' => 'Votre message',
            'constraints' => [
                new NotBlank(message: 'Le message ne peut pas être vide.'),
                new Length(['max' => 5000, 'maxMessage' => 'Le message ne peut pas dépasser {{ limit }} caractères.']),
            ],
            'attr' => $attr + ['placeholder' => 'Écrivez votre question ou message…'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MessageEvenement::class,
            'csrf_protection' => false,
        ]);
    }
}
