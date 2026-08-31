<?php

namespace App\Manager;

use App\Entity\User;
use App\Exception\UserAlreadyExistsException;
use App\Model\Request\UpdateMeRequestModel;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

readonly class UserManager
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasherFactoryInterface $passwordHasherFactory
    ) {}

    /**
     * @throws UserAlreadyExistsException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function register(User $user): User
    {
        if (null !== $this->userRepository->findOneByEmailOrUsername($user)) {
            throw new UserAlreadyExistsException();
        }

        $user->password = $this->passwordHasherFactory->getPasswordHasher(User::class)->hash($user->password, '');
        $this->userRepository->save($user);

        return $user;
    }

    /**
     * @throws UserAlreadyExistsException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function update(User $user, UpdateMeRequestModel $model): void
    {
        if (null !== $this->userRepository->findOtherByEmailOrUsername($user, $model->email, $model->username)) {
            throw new UserAlreadyExistsException();
        }

        $user->username = $model->username;
        $user->email = $model->email;

        if ('' !== trim($model->password ?? '')) {
            $user->password = $this->passwordHasherFactory->getPasswordHasher(User::class)->hash($model->password, '');
        }

        $this->userRepository->save($user);
    }
}
