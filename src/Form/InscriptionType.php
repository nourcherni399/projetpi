<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Sexe;
use App\Enum\UserRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Formulaire d'inscription publique.
 * Seuls les rôles Patient, Parent et Utilisateur sont proposés.
 * Les comptes Admin et Médecin ne peuvent être créés que par un administrateur (interface /admin/utilisateurs/new).
 */
final class InscriptionType extends AbstractType
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
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'constraints' => [
                    new NotBlank(message: 'L\'email est obligatoire.'),
                    new Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.'),
                    new Length(['max' => 180, 'maxMessage' => 'L\'email ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => $attr + ['placeholder' => 'exemple@email.com'],
            ])
            ->add('telephone', IntegerType::class, [
                'label' => 'Téléphone',
                'constraints' => [
                    new NotBlank(message: 'Le téléphone est obligatoire.'),
                    new Range(['min' => 10000000, 'max' => 999999999999, 'notInRangeMessage' => 'Le téléphone doit contenir entre 8 et 12 chiffres.']),
                ],
                'attr' => $attr + ['placeholder' => '612345678'],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => $attr + ['placeholder' => 'Minimum 6 caractères'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => $attr + ['placeholder' => 'Confirmer'],
                ],
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Length([
                        'min' => 6,
                        'max' => 4096,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
            ])
            ->add('role', EnumType::class, [
                'label' => 'Vous êtes',
                'class' => UserRole::class,
                'choice_label' => fn (UserRole $r) => match ($r) {
                    UserRole::PARENT => 'Parent / Proche',
                    UserRole::PATIENT => 'Personne concernée (patient)',
                    UserRole::USER => 'Utilisateur',
                    default => $r->value,
                },
                'choices' => [UserRole::PATIENT, UserRole::PARENT, UserRole::USER],
                'placeholder' => 'Sélectionnez votre profil',
                'constraints' => [new NotBlank(message: 'Veuillez sélectionner un profil.')],
                'attr' => $attr + ['data-role-select' => '1'],
            ])
            ->add('relationAvecPatient', TextType::class, [
                'label' => 'Relation avec le patient',
                'required' => false,
                'constraints' => [new Length(['max' => 100])],
                'attr' => $attr + ['placeholder' => 'Ex. Père, Mère, Tuteur', 'data-role-fields' => 'ROLE_PARENT'],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'required' => false,
                'help' => 'La date ne doit pas être dans le futur.',
                'constraints' => [new LessThanOrEqual(new \DateTimeImmutable('today'), message: 'La date de naissance ne peut pas être dans le futur.')],
                'attr' => $attr + ['data-role-fields' => 'ROLE_PATIENT', 'max' => (new \DateTimeImmutable('today'))->format('Y-m-d')],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'constraints' => [new Length(['max' => 500])],
                'attr' => $attr + ['placeholder' => 'Adresse', 'data-role-fields' => 'ROLE_PATIENT'],
            ])
            ->add('sexe', EnumType::class, [
                'label' => 'Sexe',
                'class' => Sexe::class,
                'choice_label' => fn (Sexe $s) => $s->value,
                'placeholder' => 'Choisir',
                'required' => false,
                'attr' => $attr + ['data-role-fields' => 'ROLE_PATIENT'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Créer mon compte',
                'attr' => ['class' => 'w-full py-3 rounded-lg bg-[#A7C7E7] text-white font-medium hover:bg-[#B8D4ED] focus:outline focus:ring-2 focus:ring-[#A7C7E7] cursor-pointer'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}