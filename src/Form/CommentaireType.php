<?php

namespace App\Form;

use App\Entity\Commentaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
class CommentaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contenu', TextareaType::class, [
                'label' => false,
                'empty_data' => '',
                'required' => false,
                'constraints' => [
                    new Length([
                        'min' => 0,
                        'max' => 2000,
                        'maxMessage' => 'Le commentaire ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => [
                    'placeholder' => 'Écrivez votre commentaire ici...',
                    'class' => 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#A7C7E7] focus:border-transparent resize-none',
                    'rows' => 3
                ]
            ])
            ->add('mediaFile', FileType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '50M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                            'video/mp4',
                            'video/webm',
                            'video/quicktime',
                        ],
                        'mimeTypesMessage' => 'Le fichier doit être une image (JPEG, PNG, GIF, WebP) ou une vidéo (MP4, WebM).',
                        'maxSizeMessage' => 'Le fichier ne doit pas dépasser 50 Mo.',
                    ]),
                ],
                'attr' => [
                    'class' => 'comment-media-input sr-only',
                    'accept' => 'image/*,video/*',
                ],
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, function (\Symfony\Component\Form\FormEvent $event): void {
                $form = $event->getForm();
                $contenu = trim((string) ($form->get('contenu')->getData() ?? ''));
                $mediaFile = $form->get('mediaFile')->getData();
                if ($contenu === '' && $mediaFile === null) {
                    $form->get('contenu')->addError(new FormError('Ajoutez du texte ou une image/vidéo.'));
                }
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commentaire::class,
        ]);
    }
}