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
        return \in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admin: accès total
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Client: accès uniquement à ses propres orders
        $order = $subject; /** @var Order $order */
        $userClient = method_exists($user, 'getClient') ? $user->getClient() : null;

        if (null === $userClient) {
            return false;
        }

        // Même client → OK pour VIEW et EDIT (les champs visibles sont déjà restreints par le FormType)
        return $order->getClient() === $userClient;
    }
}
