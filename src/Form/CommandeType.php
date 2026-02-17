<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $lockUserFields = $options['lock_user_fields'] ?? false;

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom complet',
                'disabled' => $lockUserFields,
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire']),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Votre nom complet'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'disabled' => $lockUserFields,
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est obligatoire']),
                    new Email(['message' => 'Veuillez entrer une adresse email valide']),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'votre@email.com'],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Numéro de téléphone',
                'disabled' => $lockUserFields,
                'constraints' => [
                    new NotBlank(['message' => 'Le téléphone est obligatoire']),
                    new Regex([
                        'pattern' => '/^[0-9\s\-\+()]{8,}$/',
                        'message' => 'Veuillez entrer un numéro de téléphone valide',
                    ]),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => '+212 6XX XXX XXX'],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'constraints' => [
                    new NotBlank(['message' => 'L\'adresse est obligatoire']),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Numéro et rue'],
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'constraints' => [
                    new NotBlank(['message' => 'Le code postal est obligatoire']),
                    new Regex([
                        'pattern' => '/^\d{5}$/',
                        'message' => 'Le code postal doit contenir 5 chiffres',
                    ]),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => '12345'],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'constraints' => [
                    new NotBlank(['message' => 'La ville est obligatoire']),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'Votre ville'],
            ])
            ->add('modePayment', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices' => [
                    'Visa' => 'carte_visa',
                    'MasterCard' => 'carte_mastercard',
                    'American Express' => 'carte_amex',
                    'À la livraison (paiement à réception)' => 'a_la_livraison',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un mode de paiement']),
                ],
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\Entity\Commande::class,
            'lock_user_fields' => false,
        ]);
        $resolver->setAllowedTypes('lock_user_fields', 'bool');
    }
}
