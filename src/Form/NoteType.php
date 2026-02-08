<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Note;
use App\Entity\Patient;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class NoteType extends AbstractType
{
    /**
     * @param array{patients: list<Patient>} $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('patient', EntityType::class, [
                'label' => 'Patient',
                'class' => Patient::class,
                'choices' => $options['patients'],
                'choice_label' => fn (Patient $p) => $p->getNom() . ' ' . $p->getPrenom(),
                'placeholder' => 'Choisir un patient',
                'constraints' => [new NotBlank(message: 'Le patient est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu de la note',
                'constraints' => [
                    new NotBlank(message: 'Le contenu est obligatoire.'),
                    new Length(['min' => 1, 'max' => 65535, 'maxMessage' => 'Le contenu ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => array_merge($attr, ['rows' => 4]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Note::class,
            'patients' => [],
        ]);
        $resolver->setAllowedTypes('patients', 'array');
    }
}
