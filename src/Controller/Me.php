<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/me', methods: ['GET'])]
class Me extends Api
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage
    ) {}

    public function __invoke(): Response
    {
        $user = $this->tokenStorage->getToken()->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return $this->buildSerializedResponse([
            'user' => $user,
        ]);
    }
}
