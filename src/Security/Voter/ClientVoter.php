<?php
namespace App\Security\Voter;

use App\Entity\Client;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ClientVoter extends Voter
{
    public const VIEW = 'CLIENT_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Client;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) return false;

        // Les admins voient tout
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) return true;

        // Clients : doivent être approuvés et ne voir QUE leur client
        if (in_array('ROLE_CLIENT', $user->getRoles(), true)) {
            return $user->getClient() && $user->getClient()->getId() === $subject->getId();
        }

        return false;
    }
}
