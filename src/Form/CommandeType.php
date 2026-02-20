<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
                'label' => 'Mode de Paiement *',
                'choices' => [
                    'Paiement à la livraison' => 'a_la_livraison',
                    'Carte Bancaire' => 'carte_bancaire',
                ],
                'expanded' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un mode de paiement']),
                ],
                'attr' => ['class' => 'space-y-3'],
            ])
            ->add('numeroCarte', TextType::class, [
                'label' => 'Numéro de Carte (16 chiffres) *',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '0000 0000 0000 0000', 'maxlength' => 19],
            ])
            ->add('dateExpiration', TextType::class, [
                'label' => 'Date d\'Expiration (MM/YY) *',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'MM/YY', 'maxlength' => 5],
            ])
            ->add('cvv', TextType::class, [
                'label' => 'CVV (3-4 chiffres) *',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '***', 'maxlength' => 4, 'inputmode' => 'numeric'],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            if ($form->get('modePayment')->getData() !== 'carte_bancaire') {
                return;
            }
            $numero = trim((string) $form->get('numeroCarte')->getData());
            $numeroChiffres = preg_replace('/\s+/', '', $numero);
            if ($numero === '' || $numeroChiffres === '') {
                $form->get('numeroCarte')->addError(new FormError('Le numéro de carte est obligatoire.'));
            } elseif (!preg_match('/^\d{16}$/', $numeroChiffres)) {
                $form->get('numeroCarte')->addError(new FormError('Le numéro de carte doit contenir 16 chiffres.'));
            }

            $date = trim((string) $form->get('dateExpiration')->getData());
            if ($date === '') {
                $form->get('dateExpiration')->addError(new FormError('La date d\'expiration est obligatoire.'));
            } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $date)) {
                $form->get('dateExpiration')->addError(new FormError('La date doit être au format MM/YY (ex. 12/28).'));
            }

            $cvv = trim((string) $form->get('cvv')->getData());
            if ($cvv === '') {
                $form->get('cvv')->addError(new FormError('Le CVV est obligatoire.'));
            } elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
                $form->get('cvv')->addError(new FormError('Le CVV doit contenir 3 ou 4 chiffres.'));
            }
        });
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
