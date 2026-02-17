<?php

namespace App\Form;

use App\Entity\Module;
use App\Entity\Ressource;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RessourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire.'),
                    new Length(max: 255, maxMessage: 'Le titre ne peut pas depasser {{ limit }} caracteres.'),
                ],
                'attr' => $attr + ['placeholder' => 'Ex. Video sur la communication'],
            ])
            ->add('typeRessource', ChoiceType::class, [
                'label' => 'Type de ressource',
                'choices' => [
                    'URL' => 'url',
                    'Video' => 'video',
                    'Audio' => 'audio',
                ],
                'placeholder' => 'Choisir un type',
                'constraints' => [
                    new NotBlank(message: 'Le type de ressource est obligatoire.'),
                    new Choice(choices: ['url', 'video', 'audio'], message: 'Type invalide.'),
                ],
                'attr' => $attr,
            ])
            ->add('contenu', TextType::class, [
                'label' => 'Contenu (URL ou chemin media)',
                'required' => false,
                'attr' => $attr + ['placeholder' => 'https://...'],
            ])
            ->add('mediaFile', FileType::class, [
                'label' => 'Fichier video/audio (optionnel)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '100M',
                        'mimeTypes' => [
                            'video/mp4',
                            'video/webm',
                            'video/quicktime',
                            'audio/mpeg',
                            'audio/wav',
                            'audio/ogg',
                            'audio/x-m4a',
                            'audio/mp4',
                        ],
                        'mimeTypesMessage' => 'Le fichier doit etre une video ou un audio valide.',
                        'maxSizeMessage' => 'Le fichier ne doit pas depasser 100MB.',
                    ]),
                ],
                'attr' => ['class' => 'mt-1 block w-full text-[#4B5563]', 'accept' => 'video/*,audio/*'],
            ])
            ->add('module', EntityType::class, [
                'label' => 'Module associe',
                'class' => Module::class,
                'choice_label' => 'titre',
                'placeholder' => 'Choisir un module',
                'constraints' => [
                    new NotBlank(message: 'Le module est obligatoire.'),
                ],
                'attr' => $attr,
            ])
            ->add('ordre', IntegerType::class, [
                'label' => "Ordre d'affichage",
                'required' => false,
                'attr' => $attr + ['placeholder' => 'Ex. 1'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => ['class' => 'rounded border-[#E5E0D8] text-[#A7C7E7] focus:ring-[#A7C7E7]'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ressource::class,
        ]);
    }
}
