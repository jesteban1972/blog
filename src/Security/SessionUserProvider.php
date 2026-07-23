<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Security/SessionUserProvider.php

/**
 * this is the consolidated, universal blueprint file that can be dropped verbatim
 * into every client application.
 */

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * bridge between the local session and the Symfony Security component.
 * handles users authenticated externally via SSO/JWT claims and orchestrates
 * promotion from stateless SessionUser to local App\Entity\User.
 */
class SessionUserProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {}

    public function supportsClass(string $class): bool
    {
        return $class === SessionUser::class || $class === User::class;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        // retrieve numerical ID safely across both User entity and SessionUser DTO
        $id = method_exists($user, 'getId') ? $user->getId() : $user->getUserIdentifier();

        $this->logger->info('refreshing user {id}', ['id' => $id]);

        // check for persistent shadow entity in local DB
        $userEntity = $id ? $this->entityManager->getRepository(User::class)->find($id) : null;

        if ($userEntity) {
            $this->logger->info('shadow entity found, promoting user.', ['id' => $id]);
            return $this->hydrate($userEntity);
        }

        // re-hydrate SessionUser from active session claims to capture mid-session updates (e.g. consent status)
        if ($user instanceof SessionUser) {
            $sessionData = $this->getSsoDataFromSession();
            return new SessionUser($sessionData);
        }

        $this->logger->notice('no shadow entity found for {id}, remaining SessionUser.', ['id' => $id]);

        return $user;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $sessionData = $this->getSsoDataFromSession();

        if (empty($sessionData['id'])) {
            throw new UserNotFoundException(sprintf('no SSO session data for id "%s".', $identifier));
        }

        // check for persistent shadow entity using numerical SSO id
        $user = $this->entityManager->getRepository(User::class)->find($identifier);

        if ($user) {
            $this->logger->info('shadow entity found, promoting user.', ['id' => $identifier]);
            return $this->hydrate($user, $sessionData);
        }

        $this->logger->notice('no shadow entity found, reconstructing SessionUser.', ['id' => $identifier]);

        return new SessionUser([
            'id' => (int) $identifier,
            'email' => $sessionData['email'] ?? null,
            'roles' => $sessionData['roles'] ?? ['ROLE_USER'],
            'username' => $sessionData['username'] ?? null,
            'uxLanguage' => $sessionData['uxLanguage'] ?? 'en',
            'isConsented' => $sessionData['isConsented'] ?? false,
        ]);
    }

    private function hydrate(User $user, ?array $data = null): User
    {
        $sessionData = $data ?? $this->getSsoDataFromSession();

        if (method_exists($user, 'setUsername')) {
            $user->setUsername($sessionData['username'] ?? null);
        }
        if (method_exists($user, 'setEmail')) {
            $user->setEmail($sessionData['email'] ?? null);
        }
        if (method_exists($user, 'setRoles')) {
            $user->setRoles($sessionData['roles'] ?? ['ROLE_USER']);
        }
        if (method_exists($user, 'setUxLanguage')) {
            $user->setUxLanguage($sessionData['uxLanguage'] ?? 'en');
        }
        if (method_exists($user, 'setIsConsented')) {
            $user->setIsConsented($sessionData['isConsented'] ?? false);
        }

        return $user;
    }

    private function getSsoDataFromSession(): array
    {
        $data = $this->requestStack->getSession()->get('user');

        // case A: session holds an existing SessionUser DTO
        if ($data instanceof SessionUser) {
            return [
                'id' => $data->getId(),
                'username' => $data->getUsername(),
                'email' => $data->getEmail(),
                'roles' => $data->getRoles(),
                'uxLanguage' => $data->getUxLanguage(),
                'isConsented' => $data->isConsented(),
            ];
        }

        // case B: session holds raw array from JWT introspection or refresh
        if (is_array($data)) {
            return [
                'id' => $data['id'] ?? $data['sub'] ?? null,
                'username' => $data['username'] ?? null,
                'email' => $data['email'] ?? null,
                'roles' => $data['roles'] ?? ['ROLE_USER'],
                'uxLanguage' => $data['uxLanguage'] ?? 'en',
                'isConsented' => (bool) ($data['isConsented'] ?? false),
            ];
        }

        // case C: missing or corrupted session state
        $errorMessage = 'SSO session data is missing or corrupted.';
        $this->logger->error($errorMessage);

        throw new UserNotFoundException($errorMessage);
    }
}
