<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => $attr + ['placeholder' => 'Ex. Atelier sensoriel'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'constraints' => [new Length(['max' => 65535, 'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['rows' => 4, 'placeholder' => 'Description de l\'événement…'],
            ])
            ->add('dateEvent', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'La date est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('heureDebut', TimeType::class, [
                'label' => 'Heure de début',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'L\'heure de début est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('heureFin', TimeType::class, [
                'label' => 'Heure de fin',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'L\'heure de fin est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'constraints' => [new Length(['max' => 255, 'maxMessage' => 'Le lieu ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['placeholder' => 'Ex. Salle principale (optionnel)'],
            ])
            ->add('thematique', EntityType::class, [
                'label' => 'Thématique',
                'class' => \App\Entity\Thematique::class,
                'choice_label' => 'nomThematique',
                'placeholder' => 'Choisir une thématique (optionnel)',
                'required' => false,
                'attr' => $attr,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
