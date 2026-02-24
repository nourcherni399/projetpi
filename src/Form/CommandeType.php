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
        $stripeConfigured = $options['stripe_configured'] ?? false;

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
                    new Regex([
                        'pattern' => '/@(gmail\.com|icloud\.com|icloud\.fr)$/i',
                        'message' => 'L\'adresse email doit se terminer par @gmail.com ou @icloud.com',
                    ]),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => 'exemple@gmail.com'],
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
                    'Paiement à la livraison' => 'a_la_livraison',
                    'Carte Bancaire' => 'carte_bancaire',
                ],
                'expanded' => true,
                'multiple' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez choisir un mode de paiement']),
                ],
                'attr' => ['class' => 'space-y-2'],
            ]);

        $builder->add('cardNumber', TextType::class, [
            'label' => 'Numéro de carte',
            'mapped' => false,
            'required' => false,
            'attr' => [
                'class' => 'form-control w-full px-4 py-2 border border-[#E5E0D8] rounded-lg',
                'placeholder' => '1234 5678 9012 3456',
                'maxlength' => 19,
                'autocomplete' => 'cc-number',
            ],
        ]);
        $builder->add('cardExpiry', TextType::class, [
            'label' => 'Date d\'expiration (MM/YY)',
            'mapped' => false,
            'required' => false,
            'attr' => [
                'class' => 'form-control w-full px-4 py-2 border border-[#E5E0D8] rounded-lg',
                'placeholder' => 'MM/YY',
                'maxlength' => 5,
                'autocomplete' => 'cc-exp',
            ],
        ]);
        $builder->addEventListener(FormEvents::POST_SUBMIT, function ($event) use ($stripeConfigured): void {
            $form = $event->getForm();
            $commande = $form->getData();
            if (!$commande || ($commande->getModePayment() ?? '') !== 'carte_bancaire') {
                return;
            }
            if ($stripeConfigured) {
                return;
            }
            $cardNumber = trim((string) ($form->get('cardNumber')->getData() ?? ''));
            $cardExpiry = trim((string) ($form->get('cardExpiry')->getData() ?? ''));
            if (!$this->luhnCheck($cardNumber)) {
                $form->get('cardNumber')->addError(new FormError('Numéro de carte invalide'));
            }
            if (!$this->validateExpiry($cardExpiry)) {
                $form->get('cardExpiry')->addError(new FormError('Format invalide (MM/YY requis, date future)'));
            }
        });
    }

    private function luhnCheck(string $num): bool
    {
        $n = preg_replace('/\D/', '', $num) ?? '';
        if (strlen($n) < 13 || strlen($n) > 19) {
            return false;
        }
        $sum = 0;
        $alt = false;
        for ($i = strlen($n) - 1; $i >= 0; --$i) {
            $d = (int) $n[$i];
            if ($alt) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $alt = !$alt;
        }
        return $sum % 10 === 0;
    }

    private function validateExpiry(string $val): bool
    {
        if (!preg_match('/^(\d{2})\/(\d{2})$/', $val, $m)) {
            return false;
        }
        $mm = (int) $m[1];
        $yy = (int) $m[2] + 2000;
        if ($mm < 1 || $mm > 12) {
            return false;
        }
        $now = new \DateTimeImmutable();
        return $yy > (int) $now->format('Y') || ($yy === (int) $now->format('Y') && $mm >= (int) $now->format('m'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\Entity\Commande::class,
            'lock_user_fields' => false,
            'stripe_configured' => false,
        ]);
        $resolver->setAllowedTypes('lock_user_fields', 'bool');
        $resolver->setAllowedTypes('stripe_configured', 'bool');
    }
}
