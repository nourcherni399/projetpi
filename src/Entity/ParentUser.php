<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ParentUserRepository;
use Doctrine\ORM\Mapping as ORM;

/** Entité Parent (diagramme UML) — nommée ParentUser car "Parent" est réservé en PHP. */
#[ORM\Entity(repositoryClass: ParentUserRepository::class)]
class ParentUser extends User
{
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $relationAvecPatient = null;

    public function getRelationAvecPatient(): ?string
    {
        return $this->relationAvecPatient;
    }

    public function setRelationAvecPatient(?string $relationAvecPatient): static
    {
        $this->relationAvecPatient = $relationAvecPatient;
        return $this;
    }
}
