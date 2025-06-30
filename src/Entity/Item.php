<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $important = false;

    #[ORM\OneToMany(mappedBy: 'item', targetEntity: TripItem::class, cascade: ['persist', 'remove'])]
    private Collection $tripItems;

    public function __construct()
    {
        $this->tripItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isImportant(): bool
    {
        return $this->important;
    }

    public function setImportant(bool $important): self
    {
        $this->important = $important;
        return $this;
    }

    /**
     * @return Collection|TripItem[]
     */
    public function getTripItems(): Collection
    {
        return $this->tripItems;
    }

    public function addTripItem(TripItem $tripItem): self
    {
        if (!$this->tripItems->contains($tripItem)) {
            $this->tripItems->add($tripItem);
            $tripItem->setItem($this);
        }
        return $this;
    }

    public function removeTripItem(TripItem $tripItem): self
    {
        if ($this->tripItems->removeElement($tripItem)) {
            if ($tripItem->getItem() === $this) {
                $tripItem->setItem(null);
            }
        }
        return $this;
    }
}
