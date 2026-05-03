<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\UserAlreadyExistsException;
use App\Mail\RegisterMail;
use App\Manager\UserManager;
use App\Model\Request\RegisterRequestModel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/register', methods: ['POST'])]
class Register extends Api
{
    public function __construct(
        private readonly UserManager $userManager
    ) {}

    public function __invoke(RegisterRequestModel $model): Response
    {
        $this->validate($model);

        $user = new User();
        $user->username = $model->username;
        $user->email = $model->email;
        $user->password = $model->password;

        try {
            $this->userManager->register($user);

            $this->sendMail($user->email, new RegisterMail(['username' => $user->username]));

            return $this->buildSerializedResponse($user);
        } catch (UserAlreadyExistsException $e) {
            throw new ConflictHttpException();
        }
    }
}
