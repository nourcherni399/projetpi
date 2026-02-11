<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Sexe;
use App\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\When;
use App\Entity\User;

final class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => $attr + ['placeholder' => 'Nom'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => $attr + ['placeholder' => 'Prénom'],
            ])
            ->add('email', TextType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(message: 'L\'email est obligatoire.'),
                    new Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.'),
                    new Length(['max' => 180, 'maxMessage' => 'L\'email ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => $attr + ['placeholder' => 'email@exemple.fr'],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'constraints' => [
                    new NotBlank(message: 'Le téléphone est obligatoire.'),
                    new Regex(['pattern' => '/^\d{8,12}$/', 'message' => 'Le téléphone doit contenir entre 8 et 12 chiffres.']),
                ],
                'attr' => $attr + ['placeholder' => '612345678'],
            ])
        ;
        $builder->get('telephone')->addModelTransformer(new CallbackTransformer(
            static fn (?int $v) => $v !== null ? (string) $v : '',
            static fn (?string $v) => $v !== null && $v !== '' ? (int) $v : 0,
        ));
        $builder
            ->add('image', FileType::class, [
                'label' => 'Photo de profil',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF ou WebP).',
                        'maxSizeMessage' => 'L\'image ne doit pas dépasser 5 Mo.',
                        'uploadErrorMessage' => 'Une erreur est survenue lors de l\'upload.',
                    ]),
                ],
                'attr' => $attr + ['accept' => 'image/*'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => $attr + ['placeholder' => 'Laisser vide pour ne pas changer'],
                ],
                'second_options' => [
                    'label' => 'Confirmer',
                    'attr' => $attr + ['placeholder' => 'Confirmer'],
                ],
                'constraints' => [
                    new When(
                        expression: 'value != null and value != ""',
                        constraints: [
                            new Length([
                                'min' => 6,
                                'max' => 4096,
                                'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                                'maxMessage' => 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
                            ]),
                        ]
                    ),
                ],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
                'attr' => ['class' => 'rounded border-[#E5E0D8] text-[#A7C7E7] focus:ring-[#A7C7E7]'],
                'label_attr' => ['class' => 'text-[#4B5563]'],
            ])
            ->add('role', EnumType::class, [
                'label' => 'Rôle',
                'class' => UserRole::class,
                'choice_label' => fn (UserRole $r) => match ($r) {
                    UserRole::ADMIN => 'Administrateur',
                    UserRole::PARENT => 'Parent',
                    UserRole::PATIENT => 'Patient',
                    UserRole::MEDECIN => 'Médecin',
                    UserRole::USER => 'Utilisateur',
                },
                'attr' => $attr + ['data-role-select' => '1'],
            ])
            ->add('specialite', TextType::class, [
                'label' => 'Spécialité',
                'required' => false,
                'mapped' => false,
                'constraints' => [new Length(['max' => 255, 'maxMessage' => 'La spécialité ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('nomCabinet', TextType::class, [
                'label' => 'Nom du cabinet',
                'required' => false,
                'mapped' => false,
                'constraints' => [new Length(['max' => 255, 'maxMessage' => 'Le nom du cabinet ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('adresseCabinet', TextType::class, [
                'label' => 'Adresse du cabinet',
                'required' => false,
                'mapped' => false,
                'constraints' => [new Length(['max' => 500, 'maxMessage' => 'L\'adresse ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('telephoneCabinet', TextType::class, [
                'label' => 'Téléphone du cabinet',
                'required' => false,
                'mapped' => false,
                'constraints' => [new Length(['max' => 30]), new Regex(['pattern' => '/^[\d\s\-\+\.\(\)]*$/', 'message' => 'Le téléphone ne doit contenir que des chiffres et espaces.'])],
                'attr' => $attr + ['data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('tarifConsultation', NumberType::class, [
                'label' => 'Tarif consultation (€)',
                'required' => false,
                'mapped' => false,
                'constraints' => [new Range(['min' => 0, 'max' => 99999.99, 'notInRangeMessage' => 'Le tarif doit être entre {{ min }} et {{ max }}.'])],
                'attr' => $attr + ['data-role-fields' => 'ROLE_MEDECIN', 'step' => '0.01'],
            ])
            ->add('relationAvecPatient', TextType::class, [
                'label' => 'Relation avec le patient',
                'required' => false,
                'mapped' => false,
                'constraints' => [new Length(['max' => 100, 'maxMessage' => 'Ce champ ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['data-role-fields' => 'ROLE_PARENT'],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'required' => false,
                'mapped' => false,
                'constraints' => [new LessThanOrEqual(new \DateTimeImmutable('today'), message: 'La date de naissance ne peut pas être dans le futur.')],
                'attr' => $attr + ['data-role-fields' => 'ROLE_PATIENT'],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'mapped' => false,
                'constraints' => [new Length(['max' => 500, 'maxMessage' => 'L\'adresse ne peut pas dépasser {{ limit }} caractères.'])],
                'attr' => $attr + ['data-role-fields' => 'ROLE_PATIENT'],
            ])
            ->add('sexe', EnumType::class, [
                'label' => 'Sexe',
                'class' => Sexe::class,
                'choice_label' => fn (Sexe $s) => $s->value,
                'placeholder' => 'Choisir',
                'required' => false,
                'mapped' => false,
                'attr' => $attr + ['data-role-fields' => 'ROLE_PATIENT'],
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function ($event): void {
            $data = $event->getData();
            if (!\is_array($data) || !isset($data['plainPassword'])) {
                return;
            }
            $pwd = $data['plainPassword'];
            if (!\is_array($pwd)) {
                return;
            }
            $first = isset($pwd['first']) ? trim((string) $pwd['first']) : '';
            $second = isset($pwd['second']) ? trim((string) $pwd['second']) : '';
            if ($first === '' && $second === '') {
                $data['plainPassword'] = ['first' => '', 'second' => ''];
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
