<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
<<<<<<< HEAD
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
=======
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770

final class UserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
<<<<<<< HEAD
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
=======
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.']),
                ],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => 'Nom'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
<<<<<<< HEAD
                'constraints' => [new NotBlank(message: 'Le prénom est obligatoire.')],
=======
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères.']),
                ],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => 'Prénom'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
<<<<<<< HEAD
                'constraints' => [new NotBlank(message: 'L\'email est obligatoire.')],
=======
                'constraints' => [
                    new NotBlank(message: 'L\'email est obligatoire.'),
                    new Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.'),
                    new Length(['max' => 180, 'maxMessage' => 'L\'email ne peut pas dépasser {{ limit }} caractères.']),
                ],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => 'email@exemple.fr'],
            ])
            ->add('telephone', IntegerType::class, [
                'label' => 'Téléphone',
<<<<<<< HEAD
                'constraints' => [new NotBlank(message: 'Le téléphone est obligatoire.')],
=======
                'constraints' => [
                    new NotBlank(message: 'Le téléphone est obligatoire.'),
                    new Range(['min' => 10000000, 'max' => 999999999999, 'notInRangeMessage' => 'Le téléphone doit contenir entre 8 et 12 chiffres.']),
                ],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => '612345678'],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => $attr + ['placeholder' => 'Mot de passe'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => $attr + ['placeholder' => 'Confirmer'],
                ],
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe est obligatoire.'),
<<<<<<< HEAD
                    new Length(['min' => 6, 'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.']),
                ],
=======
                    new Length([
                        'min' => 6,
                        'max' => 4096,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'data' => true,
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
                'placeholder' => 'Choisir un rôle',
                'constraints' => [new NotBlank(message: 'Le rôle est obligatoire.')],
                'attr' => $attr + ['data-role-select' => '1'],
            ])
            // — Attributs Médecin
            ->add('specialite', TextType::class, [
                'label' => 'Spécialité',
                'required' => false,
<<<<<<< HEAD
=======
                'constraints' => [new Length(['max' => 255, 'maxMessage' => 'La spécialité ne peut pas dépasser {{ limit }} caractères.'])],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => 'Ex. Pédopsychiatrie', 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('nomCabinet', TextType::class, [
                'label' => 'Nom du cabinet',
                'required' => false,
<<<<<<< HEAD
=======
                'constraints' => [new Length(['max' => 255, 'maxMessage' => 'Le nom du cabinet ne peut pas dépasser {{ limit }} caractères.'])],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => 'Nom du cabinet', 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('adresseCabinet', TextType::class, [
                'label' => 'Adresse du cabinet',
                'required' => false,
<<<<<<< HEAD
=======
                'constraints' => [new Length(['max' => 500, 'maxMessage' => 'L\'adresse ne peut pas dépasser {{ limit }} caractères.'])],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => 'Adresse', 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('telephoneCabinet', TextType::class, [
                'label' => 'Téléphone du cabinet',
                'required' => false,
<<<<<<< HEAD
=======
                'constraints' => [
                    new Length(['max' => 30]),
                    new Regex(['pattern' => '/^[\d\s\-\+\.\(\)]*$/', 'message' => 'Le téléphone ne doit contenir que des chiffres et espaces.']),
                ],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => '01 23 45 67 89', 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('tarifConsultation', NumberType::class, [
                'label' => 'Tarif consultation (€)',
                'required' => false,
<<<<<<< HEAD
                'html5' => true,
=======
                'constraints' => [new Range(['min' => 0, 'max' => 99999.99, 'notInRangeMessage' => 'Le tarif doit être entre {{ min }} et {{ max }}.'])],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => '0', 'step' => '0.01', 'min' => 0, 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            // — Attributs Parent
            ->add('relationAvecPatient', TextType::class, [
                'label' => 'Relation avec le patient',
                'required' => false,
<<<<<<< HEAD
=======
                'constraints' => [new Length(['max' => 100, 'maxMessage' => 'Ce champ ne peut pas dépasser {{ limit }} caractères.'])],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => 'Ex. Père, Mère, Tuteur', 'data-role-fields' => 'ROLE_PARENT'],
            ])
            // — Attributs Patient / Utilisateur
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'required' => false,
<<<<<<< HEAD
=======
                'constraints' => [new LessThanOrEqual(new \DateTimeImmutable('today'), message: 'La date de naissance ne peut pas être dans le futur.')],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['data-role-fields' => 'ROLE_PATIENT'],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
<<<<<<< HEAD
=======
                'constraints' => [new Length(['max' => 500, 'maxMessage' => 'L\'adresse ne peut pas dépasser {{ limit }} caractères.'])],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => 'Adresse', 'data-role-fields' => 'ROLE_PATIENT'],
            ])
            ->add('sexe', TextType::class, [
                'label' => 'Sexe',
                'required' => false,
<<<<<<< HEAD
=======
                'constraints' => [new Length(['max' => 20, 'maxMessage' => 'Ce champ ne peut pas dépasser {{ limit }} caractères.'])],
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
                'attr' => $attr + ['placeholder' => 'Ex. M, F', 'data-role-fields' => 'ROLE_PATIENT'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
