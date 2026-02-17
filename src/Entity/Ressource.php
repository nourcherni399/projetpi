<?php

namespace App\Entity;

use App\Repository\RessourceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: RessourceRepository::class)]
class Ressource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $titre = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le type de ressource est obligatoire.')]
    #[Assert\Choice(choices: ['url', 'video', 'audio'], message: 'Le type de ressource doit etre url, video ou audio.')]
    private ?string $typeRessource = null;

    #[ORM\Column(type: 'text')]
    private ?string $contenu = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(name: 'datemodif', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateModif = null;

    #[ORM\Column(nullable: true)]
    private ?int $ordre = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\ManyToOne(inversedBy: 'ressources')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Module $module = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->dateCreation = $now;
        $this->dateModif = $now;
        $this->isActive = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getTypeRessource(): ?string
    {
        return $this->typeRessource;
    }

    public function setTypeRessource(?string $typeRessource): static
    {
        $this->typeRessource = $typeRessource;

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDateModif(): ?\DateTimeImmutable
    {
        return $this->dateModif;
    }

    public function setDateModif(\DateTimeImmutable $dateModif): static
    {
        $this->dateModif = $dateModif;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function setModule(?Module $module): static
    {
        $this->module = $module;

        return $this;
    }

    #[Assert\Callback]
    public function validateContenuByType(ExecutionContextInterface $context): void
    {
        $type = $this->typeRessource;
        $contenu = trim((string) $this->contenu);
        if ($type === null || $contenu === '') {
            return;
        }

        if ($type === 'url') {
            if (!preg_match('#^https?://#i', $contenu)) {
                $context->buildViolation('Pour le type URL, le contenu doit commencer par http:// ou https://.')
                    ->atPath('contenu')
                    ->addViolation();
            }
            return;
        }

        $isHttpUrl = (bool) preg_match('#^https?://#i', $contenu);
        $isLocalUploadedFile = str_starts_with($contenu, 'uploads/ressources/');

        if (!$isHttpUrl && !$isLocalUploadedFile) {
            $context->buildViolation('Le contenu doit etre une URL http(s) ou un media uploade.')
                ->atPath('contenu')
                ->addViolation();
            return;
        }

        if ($type === 'video' && $isHttpUrl) {
            $isVideoUrl = (bool) preg_match('#(youtube\.com|youtu\.be|vimeo\.com|\.mp4($|\?)|\.webm($|\?)|\.mov($|\?))#i', $contenu);
            if (!$isVideoUrl) {
                $context->buildViolation('Pour le type video, fournissez une URL video valide ou uploadez un fichier video.')
                    ->atPath('contenu')
                    ->addViolation();
            }
        }

        if ($type === 'audio' && $isHttpUrl) {
            $isAudioUrl = (bool) preg_match('#(\.mp3($|\?)|\.wav($|\?)|\.ogg($|\?)|\.m4a($|\?))#i', $contenu);
            if (!$isAudioUrl) {
                $context->buildViolation('Pour le type audio, fournissez une URL audio valide ou uploadez un fichier audio.')
                    ->atPath('contenu')
                    ->addViolation();
            }
        }
    }
}
