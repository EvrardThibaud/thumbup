<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /** @var Collection<int, Invitation> */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Invitation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $invitations;

    /** @var Collection<int, Order> */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'client')]
    private Collection $orders;

    /** @var Collection<int, YoutubeChannel> */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: YoutubeChannel::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $youtubeChannels;

    public function __construct()
    {
        $this->invitations     = new ArrayCollection();
        $this->orders          = new ArrayCollection();
        $this->youtubeChannels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(Invitation $inv): self
    {
        if (!$this->invitations->contains($inv)) {
            $this->invitations->add($inv);
            $inv->setClient($this);
        }

        return $this;
    }

    public function removeInvitation(Invitation $inv): self
    {
        if ($this->invitations->removeElement($inv)) {
            if ($inv->getClient() === $this) {
                $inv->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setClient($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getClient() === $this) {
                $order->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, YoutubeChannel>
     */
    public function getYoutubeChannels(): Collection
    {
        return $this->youtubeChannels;
    }

    public function addYoutubeChannel(YoutubeChannel $channel): self
    {
        if (!$this->youtubeChannels->contains($channel)) {
            $this->youtubeChannels->add($channel);
            $channel->setClient($this);
        }

        return $this;
    }

    public function removeYoutubeChannel(YoutubeChannel $channel): self
    {
        if ($this->youtubeChannels->removeElement($channel)) {
            if ($channel->getClient() === $this) {
                $channel->setClient(null);
            }
        }

        return $this;
    }
}
