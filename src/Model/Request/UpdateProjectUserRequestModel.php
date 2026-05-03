<?php

namespace App\Model\Request;

use App\Security\ProjectPermission;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateProjectUserRequestModel extends AbstractRequestModel
{
    /**
     * @var list<string>|null
     */
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\Choice(choices: [
        ProjectPermission::ADMIN,
        ProjectPermission::WRITE,
        ProjectPermission::PULL,
        ProjectPermission::READ,
    ], multiple: true)]
    public ?array $permissions = null;
}
