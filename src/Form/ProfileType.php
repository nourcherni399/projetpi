<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Medcin;
use App\Entity\ParentUser;
use App\Entity\Patient;
use App\Entity\User;
use App\Enum\Sexe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

final class ProfileType extends AbstractType
{
    private const ATTR = [
        'class' => 'w-full px-4 py-3 rounded-xl border border-[#E5E0D8] bg-white text-[#4B5563] placeholder-[#9CA3AF] focus:outline focus:ring-2 focus:ring-[#A7C7E7] focus:border-transparent transition-all',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['data'] ?? null;

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => self::ATTR + ['placeholder' => 'Votre nom'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire.'),
                    new Length(['min' => 1, 'max' => 255, 'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères.']),
                ],
                'attr' => self::ATTR + ['placeholder' => 'Votre prénom'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'constraints' => [
                    new NotBlank(message: "L'email est obligatoire."),
                    new Email(message: "L'adresse email n'est pas valide."),
                    new Length(['max' => 180, 'maxMessage' => "L'email ne peut pas dépasser {{ limit }} caractères."]),
                ],
                'attr' => self::ATTR + ['placeholder' => 'exemple@email.com'],
            ])
            ->add('telephone', IntegerType::class, [
                'label' => 'Téléphone',
                'constraints' => [
                    new NotBlank(message: 'Le téléphone est obligatoire.'),
                    new Range(['min' => 10000000, 'max' => 999999999999, 'notInRangeMessage' => 'Le téléphone doit contenir entre 8 et 12 chiffres.']),
                ],
                'attr' => self::ATTR + ['placeholder' => '612345678'],
            ]);

        if ($user instanceof Patient) {
            $builder
                ->add('dateNaissance', DateType::class, [
                    'label' => 'Date de naissance',
                    'widget' => 'single_text',
                    'required' => false,
                    'constraints' => [new LessThanOrEqual(new \DateTimeImmutable('today'), message: 'La date ne peut pas être dans le futur.')],
                    'attr' => self::ATTR + ['max' => (new \DateTimeImmutable('today'))->format('Y-m-d')],
                ])
                ->add('adresse', TextType::class, [
                    'label' => 'Adresse',
                    'required' => false,
                    'constraints' => [new Length(['max' => 500])],
                    'attr' => self::ATTR + ['placeholder' => 'Votre adresse'],
                ])
                ->add('sexe', EnumType::class, [
                    'label' => 'Sexe',
                    'class' => Sexe::class,
                    'choice_label' => fn (Sexe $s) => $s->value,
                    'placeholder' => 'Choisir',
                    'required' => false,
                    'attr' => self::ATTR,
                ]);
        }

        if ($user instanceof ParentUser) {
            $builder->add('relationAvecPatient', TextType::class, [
                'label' => 'Relation avec le patient',
                'required' => false,
                'constraints' => [new Length(['max' => 100])],
                'attr' => self::ATTR + ['placeholder' => 'Ex. Père, Mère, Tuteur'],
            ]);
        }

        if ($user instanceof Medcin) {
            $builder
                ->add('specialite', TextType::class, [
                    'label' => 'Spécialité',
                    'required' => false,
                    'constraints' => [new Length(['max' => 255])],
                    'attr' => self::ATTR + ['placeholder' => 'Ex. Pédopsychiatrie'],
                ])
                ->add('nomCabinet', TextType::class, [
                    'label' => 'Nom du cabinet',
                    'required' => false,
                    'constraints' => [new Length(['max' => 255])],
                    'attr' => self::ATTR + ['placeholder' => 'Nom de votre cabinet'],
                ])
                ->add('adresseCabinet', TextType::class, [
                    'label' => 'Adresse du cabinet',
                    'required' => false,
                    'constraints' => [new Length(['max' => 500])],
                    'attr' => self::ATTR + ['placeholder' => 'Adresse du cabinet'],
                ])
                ->add('telephoneCabinet', TextType::class, [
                    'label' => 'Téléphone du cabinet',
                    'required' => false,
                    'constraints' => [new Length(['max' => 30])],
                    'attr' => self::ATTR + ['placeholder' => '01 23 45 67 89'],
                ])
                ->add('tarifConsultation', NumberType::class, [
                    'label' => 'Tarif consultation (€)',
                    'required' => false,
                    'constraints' => [new \Symfony\Component\Validator\Constraints\Range(['min' => 0, 'max' => 99999.99, 'notInRangeMessage' => 'Le tarif doit être entre {{ min }} et {{ max }} €.'])],
                    'attr' => self::ATTR + ['placeholder' => '80', 'step' => '0.01', 'min' => '0'],
                ]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'Enregistrer les modifications',
            'attr' => [
                'class' => 'w-full py-3.5 rounded-xl bg-[#A7C7E7] text-white font-semibold hover:bg-[#96B8DC] focus:outline focus:ring-2 focus:ring-[#A7C7E7] focus:ring-offset-2 transition-colors cursor-pointer',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}