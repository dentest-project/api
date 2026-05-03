<?php

namespace App\Controller;

use App\Exception\UserNotFoundException;
use App\Mail\ResetPasswordRequestMail;
use App\Manager\UserManager;
use App\Model\Request\ResetPasswordRequestEmailModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-password-request', methods: ['POST'])]
class ResetPasswordRequest extends Api
{
    public function __construct(
        private readonly UserManager $userManager
    ) {}

    public function __invoke(ResetPasswordRequestEmailModel $model): Response
    {
        $this->validate($model);

        try {
            $user = $this->userManager->resetPasswordRequest($model->email);

            $this->sendMail($user->email, new ResetPasswordRequestMail(['link' => sprintf(
                '%s/reset-password?code=%s',
                $this->getParameter('allowed_origin'),
                $user->resetPasswordCode
            )]));

            return new JsonResponse();
        } catch (UserNotFoundException $exception) {
            return new JsonResponse();
        }
    }
}
