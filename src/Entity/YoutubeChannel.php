<?php

namespace App\Entity;

use App\Repository\YoutubeChannelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: YoutubeChannelRepository::class)]
#[ORM\Table(name: 'youtube_channel')]
#[ORM\UniqueConstraint(name: 'uniq_client_position', columns: ['client_id', 'position'])]
class YoutubeChannel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'youtubeChannels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private string $url;

    #[ORM\Column(type: 'smallint')]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? $this->url;
    }
}
