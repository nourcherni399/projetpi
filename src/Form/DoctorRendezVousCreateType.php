<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Enum\Motif;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class DoctorRendezVousCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = [
            'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
        ];
        $patients = $options['patients'] ?? [];

        $builder
            ->add('patient', EntityType::class, [
                'label' => 'Patient existant',
                'class' => Patient::class,
                'choices' => $patients,
                'choice_label' => fn (Patient $p) => ($p->getPrenom() ?? '') . ' ' . ($p->getNom() ?? '') . ($p->getEmail() ? ' (' . $p->getEmail() . ')' : ''),
                'placeholder' => '— Nouveau patient (saisie ci-dessous) —',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(min: 2, max: 255),
                ],
                'attr' => $attr + ['placeholder' => 'Nom du patient'],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire.'),
                    new Length(min: 2, max: 255),
                ],
                'attr' => $attr + ['placeholder' => 'Prénom du patient'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'constraints' => [new Email(message: "L'email n'est pas valide.")],
                'attr' => $attr + ['placeholder' => 'email@exemple.com'],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [new Regex(pattern: '/^[\d\s\-\+\.\(\)]{0,30}$/', message: 'Caractères non autorisés.')],
                'attr' => $attr + ['placeholder' => '06 12 34 56 78'],
            ])
            ->add('notePatient', TextareaType::class, [
                'label' => 'Note (optionnel)',
                'required' => false,
                'attr' => $attr + ['placeholder' => 'Note du patient', 'rows' => 2],
            ])
            ->add('motif', EnumType::class, [
                'label' => 'Motif',
                'class' => Motif::class,
                'choice_label' => fn (Motif $m) => match ($m) {
                    Motif::URGENCE => 'Urgence',
                    Motif::SUIVIE => 'Suivi',
                    Motif::NORMAL => 'Normal',
                },
                'attr' => $attr,
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data) || empty($data['patient'])) {
                return;
            }
            $patients = $event->getForm()->getConfig()->getOption('patients');
            $patientId = $data['patient'];
            foreach ($patients as $p) {
                if ((string) $p->getId() === (string) $patientId) {
                    $data['nom'] = $p->getNom() ?? '';
                    $data['prenom'] = $p->getPrenom() ?? '';
                    $data['email'] = $p->getEmail() ?? '';
                    $data['telephone'] = $p->getTelephone() ?? null;
                    $event->setData($data);
                    break;
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
            'patients' => [],
        ]);
        $resolver->setAllowedTypes('patients', 'array');
    }
}
