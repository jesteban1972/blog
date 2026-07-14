<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Security/SessionUserProvider.php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * this class is the bridge between the local session and the Symfony Security Component. it serves as the official
 * mechanism for Symfony to handle users who have been authenticated externally (via SSO/JWT claims).it manages the
 * 'Frankenstein' promotion from a stateless SessionUser to a persisted App\Entity\User (Shadow Entity) once local
 * registration is complete.
 */
class SessionUserProvider implements UserProviderInterface
{
    /**
     * @param EntityManagerInterface $entityManager used to check for the existence of the shadow entity
     * during the session refresh cycle to facilitate user promotion.
     * @param RequestStack $requestStack grants access to the current session to retrieve volatile SSO claims (JWT data)
     *   required for the 'Frankenstein' hydration.
     * @param LoggerInterface $ssoLogger dedicated 'sso' channel logger for orchestrating and debugging the user
     *   promotion lifecycle.
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {}

    /**
     * this method informs Symfony that this provider is capable of handling the custom SessionUser class.
     *
     * @param string $class
     * @return bool
     */
    public function supportsClass(string $class): bool
    {
        return $class === SessionUser::class || $class === User::class;
    }

    /**
     * this method handles the 'self-healing' hydration on every request. it performs two critical roles:
     * 1. promotion: it wlevates a SessionUser to an App\Entity\User if a DB record exists.
     * 2. hydration: it injects volatile SSO data (email, roles, language) into the Entity.
     *
     * @param UserInterface $user the user object currently stored in the security token.
     * @return UserInterface the promoted/hydrated Entity or the fallback SessionUser.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        $identifier = $user->getUserIdentifier();
        $this->logger->info('refreshing user {id}', ['id' => $identifier]);

        // attempt to find the local shadow entity in the local pendoncete DB:
        $userEntity = $this->entityManager->getRepository(User::class)->find($identifier);

        if ($userEntity) {

            $this->logger->info('shadow entity found, promoting to \'Frankenstein\' user.');

            return $this->hydrate($userEntity);
        }

        // if it's a SessionUser, we should still re-hydrate it from the session to pick up changes in 'isConsented'
        // without a full logout:
        if ($user instanceof SessionUser) {
            $sessionData = $this->getSsoDataFromSession();
            return new SessionUser($sessionData);
        }

        // if no persistent entity is found, we continue with the stateless session object, the volatile SessionUser:

        $this->logger->notice('no shadow entity found for {id}, staying as SessionUser.', ['id' => $identifier]);

        return $user;
    }

    /**
     * this method is the primary entry point for user identity reconstruction following a 'tabula rasa' redirect or a
     * fresh authentication event. it orchestrates the 'Frankenstein' merger by attempting to locate a persistent shadow
     * entity (App\Entity\User) in the local DB. if found, it injects volatile SSO claims (email, roles, language) into
     * the Entity, effectively promoting the user. if no local record exists, it maintains a stateless 'SessionUser'
     * status.
     *
     * @param string $identifier the unique SSO subject (sub claim).
     * @return UserInterface a hydrated App\Entity\User or a stateless SessionUser.
     * @throws UserNotFoundException if the required SSO session data is missing or expired.
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        //dd($identifier); // it is the id (as string), not the email

        ////////////////////////////////////////////////////////////////////////////////
        /// 1. retrieve data from the session

        $sessionData = $this->getSsoDataFromSession();

        // if the session is empty, Symfony should handle this as an auth failure:
        if (empty($sessionData['id'])) {
            throw new UserNotFoundException(sprintf('no SSO session data for id \'%s\'.', $identifier));
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 2. check for the persistent shadow entity

        $user = $this->entityManager->getRepository(User::class)->find($identifier);


        ////////////////////////////////////////////////////////////////////////////////
        /// 3. retrieve the volatile SSO claims (email, roles)

        if ($user) {

            $this->logger->info('shadow entity found, promoting to \'Frankenstein\' user.', ['id' => $identifier]);

            return $this->hydrate($user, $sessionData);
        }


        ////////////////////////////////////////////////////////////////////////////////
        /// 4. reconstruct stateless SessionUser

        $this->logger->notice('no shadow entity, reconstructing stateless SessionUser.', ['id' => $identifier]);

        return new SessionUser([
            'id' => (int) $identifier,
            'email' => $sessionData['email'],
            'roles' => $sessionData['roles'],
            'username' => $sessionData['username'],
            'uxLanguage' => $sessionData['uxLanguage'],
            'isConsented' => $sessionData['isConsented'],
        ]);
    }

    /**
     * this method is an internal helper to inject volatile SSO claims into a persistent User entity. this ensures
     * fields not stored in the local DB (email, roles) are always present.
     *
     * @param User $user
     * @param array|null $data Optional pre-fetched session data.
     * @return User
     */
    private function hydrate(User $user, ?array $data = null): User
    {
        $sessionData = $data ?? $this->getSsoDataFromSession();

        // hydrate the volatile properties (email, roles, uxLanguage...) from the SSO session data:
        $user->setUsername($sessionData['username']);
        $user->setEmail($sessionData['email']);
        $user->setRoles($sessionData['roles']);
        $user->setUxLanguage($sessionData['uxLanguage']);
        $user->setIsConsented($sessionData['isConsented']);

        return $user;
    }

    /**
     * this method extracts the volatile identity claims previously captured during the SSO handshake. it serves as the
     * defensive bridge to the current session, ensuring the security context remains aware of the user's global
     * authorization status (email, roles, language). since proxy applications do not persist these credentials locally,
     * it handles both existing SessionUser objects and raw JWT introspection arrays, providing safe fallbacks for
     * anomalous or expired states.
     *
     * @return array the extracted identity claims required for user hydration.
     * @throws UserNotFoundException if the session data is missing or corrupted.
     */
    private function getSsoDataFromSession(): array
    {
        $data = $this->requestStack->getSession()->get('user');

        ////////////////////////////////////////////////////////////////////////////////
        /// case A: the session holds an existing SessionUser object

        if ($data instanceof SessionUser) {
            return [
                'id' => $data->getId(),
                'username' => $data->getUsername(),
                'email' => $data->getEmail(),
                'roles' => $data->getRoles(),
                'uxLanguage' => $data->getUxLanguage(),
                'isConsented' => $data->getIsConsented(),
            ];
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// case B: the session holds a raw array from a fresh JWT introspection/refresh

        if (is_array($data)) {

            return [
                'id' => $data['id'],
                'username' => $data['username'],
                'email' => $data['email'],
                'roles' => $data['roles'],
                'uxLanguage' => $data['uxLanguage'] ?? 'en',
                'isConsented' => $data['isConsented'],
            ];
        }


        ////////////////////////////////////////////////////////////////////////////////
        /// case C: fallback for anomalous or expired session states

        $errorMessage = 'SSO session data is missing or corrupted.';

        $this->logger->error($errorMessage);

        throw new UserNotFoundException($errorMessage);
    }
}
