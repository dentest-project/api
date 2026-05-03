<?php

namespace App\Model\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordRequestModel extends AbstractRequestModel
{
    #[Assert\Length(min: 1, max: 50, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public ?string $code = null;

    #[Assert\Length(min: 8, max: 100, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public ?string $newPassword = null;
}
