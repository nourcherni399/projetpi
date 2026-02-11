<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Disponibilite;
use App\Entity\Medcin;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Enum\Motif;
use App\Enum\StatusRendezVous;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class RendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];

        $builder
            ->add('medecin', EntityType::class, [
                'label' => 'Médecin',
                'class' => Medcin::class,
                'choice_label' => fn (Medcin $m) => ($m->getPrenom() ?? '') . ' ' . ($m->getNom() ?? ''),
                'placeholder' => 'Choisir un médecin',
                'constraints' => [new NotBlank(message: 'Le médecin est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('disponibilite', EntityType::class, [
                'label' => 'Disponibilité',
                'class' => Disponibilite::class,
                'choice_label' => function (Disponibilite $d): string {
                    $jour = $d->getJour()?->value ?? '';
                    $deb = $d->getHeureDebut()?->format('H:i') ?? '';
                    $fin = $d->getHeureFin()?->format('H:i') ?? '';
                    $med = $d->getMedecin();
                    $medLabel = $med ? $med->getPrenom() . ' ' . $med->getNom() : '';
                    return trim("{$jour} {$deb}-{$fin} ({$medLabel})");
                },
                'placeholder' => 'Choisir un créneau (optionnel)',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('patient', EntityType::class, [
                'label' => 'Patient (existant)',
                'class' => Patient::class,
                'choice_label' => fn (Patient $p) => ($p->getPrenom() ?? '') . ' ' . ($p->getNom() ?? ''),
                'placeholder' => 'Aucun (saisie manuelle ci-dessous)',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom du patient',
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(min: 2, max: 255, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'),
                ],
                'attr' => $attr + ['placeholder' => 'Nom'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom du patient',
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire.'),
                    new Length(min: 2, max: 255, minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'),
                ],
                'attr' => $attr + ['placeholder' => 'Prénom'],
            ])
            ->add('adresse', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'constraints' => [new Length(max: 500, maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères.')],
                'attr' => $attr + ['rows' => 2, 'placeholder' => 'Adresse'],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [
                    new Length(max: 30, maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères.'),
                    new Regex(pattern: '/^[\d\s\-\+\.\(\)]*$/', message: 'Le téléphone contient des caractères non autorisés.'),
                ],
                'attr' => $attr + ['placeholder' => '06 12 34 56 78'],
            ])
            ->add('notePatient', TextareaType::class, [
                'label' => 'Note patient',
                'required' => false,
                'empty_data' => 'vide',
                'constraints' => [new Length(max: 5000, maxMessage: 'La note ne peut pas dépasser {{ limit }} caractères.')],
                'attr' => $attr + ['rows' => 3, 'placeholder' => 'Notes…'],
            ])
            ->add('status', EnumType::class, [
                'label' => 'Statut',
                'class' => StatusRendezVous::class,
                'choice_label' => fn (StatusRendezVous $s) => match ($s) {
                    StatusRendezVous::EN_ATTENTE => 'En attente',
                    StatusRendezVous::CONFIRMER => 'Confirmé',
                    StatusRendezVous::ANNULER => 'Annulé',
                },
                'placeholder' => 'Choisir un statut',
                'constraints' => [new NotBlank(message: 'Le statut est obligatoire.')],
                'attr' => $attr,
            ])
            ->add('motif', EnumType::class, [
                'label' => 'Motif',
                'class' => Motif::class,
                'choice_label' => fn (Motif $m) => match ($m) {
                    Motif::URGENCE => 'Urgence',
                    Motif::SUIVIE => 'Suivi',
                    Motif::NORMAL => 'Normal',
                },
                'placeholder' => 'Choisir un motif',
                'constraints' => [new NotBlank(message: 'Le motif est obligatoire.')],
                'attr' => $attr,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
