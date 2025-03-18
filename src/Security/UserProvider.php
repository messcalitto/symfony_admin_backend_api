<?php
namespace App\Security;

use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use App\Entity\User;

class UserProvider extends EntityUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = parent::loadUserByIdentifier($identifier);

        if (!$user instanceof UserInterface) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $user;
    }
}
