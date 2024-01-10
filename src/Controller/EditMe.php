<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\UserAlreadyExistsException;
use App\Manager\UserManager;
use App\Model\Request\UpdateMeRequestModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/me', methods: ['PUT'])]
class EditMe extends Api
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserManager $userManager
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->tokenStorage->getToken()->getUser();

        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('Bearer');
        }

        /** @var UpdateMeRequestModel $model */
        $model = UpdateMeRequestModel::fromRequest($request);
        $this->validate($model);

        try {
            $this->userManager->update($user, $model);

            return new JsonResponse();
        } catch (UserAlreadyExistsException $e) {
            throw new ConflictHttpException();
        }
    }
}
