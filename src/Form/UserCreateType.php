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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

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
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
                'attr' => $attr + ['placeholder' => 'Nom'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new NotBlank(message: 'Le prénom est obligatoire.')],
                'attr' => $attr + ['placeholder' => 'Prénom'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [new NotBlank(message: 'L\'email est obligatoire.')],
                'attr' => $attr + ['placeholder' => 'email@exemple.fr'],
            ])
            ->add('telephone', IntegerType::class, [
                'label' => 'Téléphone',
                'constraints' => [new NotBlank(message: 'Le téléphone est obligatoire.')],
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
                    new Length(['min' => 6, 'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.']),
                ],
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
                'attr' => $attr + ['placeholder' => 'Ex. Pédopsychiatrie', 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('nomCabinet', TextType::class, [
                'label' => 'Nom du cabinet',
                'required' => false,
                'attr' => $attr + ['placeholder' => 'Nom du cabinet', 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('adresseCabinet', TextType::class, [
                'label' => 'Adresse du cabinet',
                'required' => false,
                'attr' => $attr + ['placeholder' => 'Adresse', 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('telephoneCabinet', TextType::class, [
                'label' => 'Téléphone du cabinet',
                'required' => false,
                'attr' => $attr + ['placeholder' => '01 23 45 67 89', 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            ->add('tarifConsultation', NumberType::class, [
                'label' => 'Tarif consultation (€)',
                'required' => false,
                'html5' => true,
                'attr' => $attr + ['placeholder' => '0', 'step' => '0.01', 'min' => 0, 'data-role-fields' => 'ROLE_MEDECIN'],
            ])
            // — Attributs Parent
            ->add('relationAvecPatient', TextType::class, [
                'label' => 'Relation avec le patient',
                'required' => false,
                'attr' => $attr + ['placeholder' => 'Ex. Père, Mère, Tuteur', 'data-role-fields' => 'ROLE_PARENT'],
            ])
            // — Attributs Patient / Utilisateur
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'required' => false,
                'attr' => $attr + ['data-role-fields' => 'ROLE_PATIENT'],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => $attr + ['placeholder' => 'Adresse', 'data-role-fields' => 'ROLE_PATIENT'],
            ])
            ->add('sexe', TextType::class, [
                'label' => 'Sexe',
                'required' => false,
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
