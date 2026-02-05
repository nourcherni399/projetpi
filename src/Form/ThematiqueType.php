<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Enum\NiveauDifficulte;
use App\Entity\Enum\PublicCible;
use App\Entity\Thematique;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ThematiqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('nomThematique', TextType::class, [
                'label' => 'Nom de la thématique',
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
                'attr' => $attr + ['placeholder' => 'Ex. Sensoriel'],
            ])
            ->add('codeThematique', TextType::class, [
                'label' => 'Code thématique',
                'constraints' => [new NotBlank(message: 'Le code est obligatoire.')],
                'attr' => $attr + ['placeholder' => 'Ex. SENS'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => $attr + ['rows' => 3, 'placeholder' => 'Description…'],
            ])
            ->add('couleur', TextType::class, [
                'label' => 'Couleur',
                'required' => false,
                'attr' => $attr + ['placeholder' => 'Ex. #A7C7E7'],
            ])
            ->add('icone', TextType::class, [
                'label' => 'Icône',
                'required' => false,
                'attr' => $attr + ['placeholder' => 'Ex. star'],
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'required' => false,
                'attr' => $attr + ['min' => 0, 'placeholder' => '0'],
            ])
            ->add('publicCible', EnumType::class, [
                'label' => 'Public cible',
                'class' => PublicCible::class,
                'choice_label' => fn (PublicCible $p) => match ($p) {
                    PublicCible::ENFANT => 'Enfant',
                    PublicCible::PARENT => 'Parent',
                    PublicCible::MEDECIN => 'Médecin',
                    PublicCible::EDUCATEUR => 'Éducateur',
                    PublicCible::AIDANT => 'Aidant',
                    PublicCible::AUTRE => 'Autre',
                },
                'placeholder' => 'Choisir un public',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('niveauDifficulte', EnumType::class, [
                'label' => 'Niveau de difficulté',
                'class' => NiveauDifficulte::class,
                'choice_label' => fn (NiveauDifficulte $n) => match ($n) {
                    NiveauDifficulte::DEBUTANT => 'Débutant',
                    NiveauDifficulte::INTERMEDIAIRE => 'Intermédiaire',
                    NiveauDifficulte::AVANCE => 'Avancé',
                },
                'placeholder' => 'Choisir un niveau',
                'required' => false,
                'attr' => $attr,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Thematique::class,
        ]);
    }
}
