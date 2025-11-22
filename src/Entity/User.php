<?php

namespace App\Entity;

use App\Entity\Client;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'This email is already used.')]
#[UniqueEntity(fields: ['client'], message: 'This client is already linked to another user.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\OneToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: "client_id", referencedColumnName: "id", nullable: true, unique: true, onDelete: "SET NULL")]
    private ?Client $client = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(length: 64)]
    private ?string $timezone = 'Europe/Paris';

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- Client ---

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $c): self
    {
        $this->client = $c;

        return $this;
    }

    // --- Basics ---

    public function getId(): ?int { return $this->id; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $dt): self { $this->createdAt = $dt; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): self { $this->isVerified = $isVerified; return $this; }

    public function getTimezone(): string { return $this->timezone ?? 'Europe/Paris'; }
    public function setTimezone(string $timezone): self { $this->timezone = $timezone; return $this; }

    // --- Roles ---

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true);
    }

    public function isClientRole(): bool
    {
        return in_array('ROLE_CLIENT', $this->roles, true);
    }

    public function getRoles(): array
    {
        return array_unique($this->roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));

        // Si user devient admin → on coupe le lien client
        if ($this->isAdmin() && $this->client !== null) {
            $this->client = null;
        }

        return $this;
    }

    // --- Password ---

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    // --- Business validation rules ---

    /**
     * - si admin → ne doit pas être lié à un client
     * - si non admin → doit être lié à exactement 1 client
     */
    #[Assert\Callback]
    public function validateClientRelation(ExecutionContextInterface $context): void
    {
        if ($this->isAdmin()) {
            if ($this->client !== null) {
                $context->buildViolation('Admin users cannot be linked to a client.')
                    ->atPath('client')
                    ->addViolation();
            }
        } else {
            if ($this->client === null) {
                $context->buildViolation('This user must be linked to a client.')
                    ->atPath('client')
                    ->addViolation();
            }
        }
    }

    /**
     * - ne peut PAS avoir ROLE_ADMIN et ROLE_CLIENT en même temps
     * - doit avoir au moins l’un des deux (admin OU client)
     */
    #[Assert\Callback]
    public function validateRoles(ExecutionContextInterface $context): void
    {
        $isAdmin  = $this->isAdmin();
        $isClient = $this->isClientRole();

        if ($isAdmin && $isClient) {
            $context->buildViolation('A user cannot have both admin and client roles at the same time.')
                ->atPath('roles')
                ->addViolation();
        }

        if (!$isAdmin && !$isClient) {
            $context->buildViolation('A user must be either admin or client.')
                ->atPath('roles')
                ->addViolation();
        }
    }
}
