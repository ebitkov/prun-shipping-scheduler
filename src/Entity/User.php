<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    /** @var non-empty-string */
    #[ORM\Column(length: 180, unique: true)]
    private string $fioUsername;

    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    private string $fioApiKey;

    /**
     * @param non-empty-string $fioUsername
     */
    public function __construct(string $fioUsername, string $password, string $fioApiKey)
    {
        $this->fioUsername = $fioUsername;
        $this->password = $password;
        $this->fioApiKey = $fioApiKey;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFioUsername(): string
    {
        return $this->fioUsername;
    }

    public function getFioApiKey(): string
    {
        return $this->fioApiKey;
    }

    public function getUserIdentifier(): string
    {
        return $this->fioUsername;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
    }
}
