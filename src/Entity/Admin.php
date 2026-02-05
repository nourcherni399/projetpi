<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AdminRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminRepository::class)]
class Admin extends User
{
    /** @var Collection<int, Module> */
    #[ORM\OneToMany(targetEntity: Module::class, mappedBy: 'admin')]
    private Collection $modules;

    public function __construct()
    {
        parent::__construct();
        $this->modules = new ArrayCollection();
    }

    /** @return Collection<int, Module> */
    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function addModule(Module $module): static
    {
        if (!$this->modules->contains($module)) {
            $this->modules->add($module);
            $module->setAdmin($this);
        }
        return $this;
    }

    public function removeModule(Module $module): static
    {
        if ($this->modules->removeElement($module)) {
            if ($module->getAdmin() === $this) {
                $module->setAdmin(null);
            }
        }
        return $this;
    }
}
