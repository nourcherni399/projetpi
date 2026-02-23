<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Enum\NiveauDifficulte;
use App\Entity\Enum\PublicCible;
use App\Entity\Thematique;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

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
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => $attr + ['placeholder' => 'Ex. Sensoriel'],
            ])
            ->add('codeThematique', TextType::class, [
                'label' => 'Code thématique',
                'constraints' => [
                    new NotBlank(message: 'Le code est obligatoire.'),
                    new Length(['min' => 1, 'max' => 50, 'maxMessage' => 'Le code ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => $attr + ['placeholder' => 'Ex. SENS'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'constraints' => [new Length(['max' => 65535, 'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['rows' => 3, 'placeholder' => 'Description…'],
            ])
            ->add('couleur', TextType::class, [
                'label' => 'Couleur',
                'required' => false,
                'constraints' => [new Length(['max' => 20, 'maxMessage' => 'La couleur ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['placeholder' => 'Ex. #A7C7E7'],
            ])
            ->add('image', FileType::class, [
                'label' => 'Image (JPG, PNG, GIF, WebP)',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez téléverser une image (JPG, PNG, GIF ou WebP).',
                    ]),
                ],
                'attr' => $attr + ['accept' => 'image/jpeg,image/png,image/gif,image/webp'],
            ])
            ->add('sousTitre', TextType::class, [
                'label' => 'Sous-titre',
                'required' => false,
                'constraints' => [new Length(['max' => 255, 'maxMessage' => 'Le sous-titre ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['placeholder' => 'Ex. Sous-titre optionnel'],
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'required' => false,
                'constraints' => [new Range(['min' => 0, 'max' => 32767, 'notInRangeMessage' => 'L\'ordre doit être entre {{ min }} et {{ max }}.'])],
                'attr' => $attr + ['min' => 0, 'placeholder' => '0'],
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Visible sur le site',
                'required' => false,
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