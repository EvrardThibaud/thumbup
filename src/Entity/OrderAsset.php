<?php
// src/Entity/OrderAsset.php

namespace App\Entity;

use App\Repository\OrderAssetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: OrderAssetRepository::class)]
#[ORM\Table(name: 'order_asset')]
#[Vich\Uploadable]
class OrderAsset
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'assets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: 'string', length: 190, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[Vich\UploadableField(mapping: 'order_asset', fileNameProperty: 'fileName', size: 'fileSize', mimeType: 'mimeType')]
    private ?File $file = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(?Order $order): self { $this->order = $order; return $this; }

    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(?string $fileName): self { $this->fileName = $fileName; return $this; }

    public function getFileSize(): ?int { return $this->fileSize; }
    public function setFileSize(?int $fileSize): self { $this->fileSize = $fileSize; return $this; }

    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): self { $this->mimeType = $mimeType; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function setFile(?File $file = null): void
    {
        $this->file = $file;
        if ($file) { $this->updatedAt = new \DateTimeImmutable(); }
    }
    public function getFile(): ?File { return $this->file; }
}
