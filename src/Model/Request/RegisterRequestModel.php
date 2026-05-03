<?php

namespace App\Model\Request;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterRequestModel extends AbstractRequestModel
{
    #[Assert\Length(min: 1, max: 50, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public ?string $username = null;

    #[Assert\Email]
    #[Assert\Length(min: 1, max: 255, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public ?string $email = null;

    #[Assert\Length(min: 8, max: 100, normalizer: 'trim')]
    #[Assert\NotBlank(normalizer: 'trim')]
    public ?string $password = null;
}
