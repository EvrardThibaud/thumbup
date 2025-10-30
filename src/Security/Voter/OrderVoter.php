<?php
namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class OrderVoter extends Voter
{
    public const VIEW = 'ORDER_VIEW';
    public const EDIT = 'ORDER_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) return false;

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) return true;

        if (in_array('ROLE_CLIENT', $user->getRoles(), true)) {
            // refus si pas approuvé ou pas de client lié
            if (!$user->getClient()) return false;

            $sameClient = $subject->getClient() && $subject->getClient()->getId() === $user->getClient()->getId();
            if (!$sameClient) return false;

            // Un client peut VOIR ses commandes ; pour EDIT, à toi d’ouvrir/fermer
            return match ($attribute) {
                self::VIEW => true,
                self::EDIT => false, // mets true si tu veux autoriser l’édition par le client
            };
        }

        return false;
    }
}
