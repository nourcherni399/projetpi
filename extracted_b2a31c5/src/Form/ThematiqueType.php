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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;

final class ThematiqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('nomThematique', TextType::class, [
                'label' => 'Nom de la th├®matique',
                'attr' => $attr + ['placeholder' => 'Ex. Sensoriel'],
            ])
            ->add('codeThematique', TextType::class, [
                'label' => 'Code th├®matique',
                'attr' => $attr + ['placeholder' => 'Ex. SENS'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => $attr + ['rows' => 3, 'placeholder' => 'DescriptionÔÇª'],
            ])
            ->add('couleur', TextType::class, [
                'label' => 'Couleur',
                'required' => true,
                'attr' => $attr + ['placeholder' => 'Ex. #A7C7E7'],
            ])
            ->add('image', FileType::class, $this->getImageFieldOptions($options, $attr))
            ->add('sousTitre', TextType::class, [
                'label' => 'Sous-titre',
                'required' => true,
                'attr' => $attr + ['placeholder' => 'Ex. Sous-titre optionnel'],
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'required' => false,
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
                    PublicCible::MEDECIN => 'M├®decin',
                    PublicCible::EDUCATEUR => '├ëducateur',
                    PublicCible::AIDANT => 'Aidant',
                    PublicCible::AUTRE => 'Autre',
                },
                'placeholder' => 'Choisir un public',
                'required' => true,
                'attr' => $attr,
            ])
            ->add('niveauDifficulte', EnumType::class, [
                'label' => 'Niveau de difficult├®',
                'class' => NiveauDifficulte::class,
                'choice_label' => fn (NiveauDifficulte $n) => match ($n) {
                    NiveauDifficulte::DEBUTANT => 'D├®butant',
                    NiveauDifficulte::INTERMEDIAIRE => 'Interm├®diaire',
                    NiveauDifficulte::AVANCE => 'Avanc├®',
                },
                'placeholder' => 'Choisir un niveau',
                'required' => true,
                'attr' => $attr,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Thematique::class,
            'constraints' => [new Valid()],
        ]);
    }

    /**
     * Image obligatoire ├á la cr├®ation, optionnelle ├á l'├®dition.
     */
    private function getImageFieldOptions(array $options, array $attr): array
    {
        $data = $options['data'] ?? null;
        $isNew = $data === null || $data->getId() === null;

        $imageOptions = [
            'label' => 'Image (JPG, PNG, GIF, WebP)',
            'mapped' => false,
            'required' => $isNew,
            'constraints' => [
                new File([
                    'maxSize' => '5M',
                    'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                    'mimeTypesMessage' => 'Veuillez t├®l├®verser une image (JPG, PNG, GIF ou WebP).',
                ]),
            ],
            'attr' => $attr + ['accept' => 'image/jpeg,image/png,image/gif,image/webp'],
        ];

        if ($isNew) {
            $imageOptions['constraints'][] = new NotBlank(message: 'Veuillez s├®lectionner une image.');
        }

        return $imageOptions;
    }
}
