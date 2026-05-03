<?php

namespace App\Model\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordRequestEmailModel extends AbstractRequestModel
{
    #[Assert\Email]
    #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public ?string $email = null;
}
