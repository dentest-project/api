<?php

namespace App\Model\Request;

use App\Security\OrganizationPermission;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateOrganizationUserRequestModel extends AbstractRequestModel
{
    /**
     * @var list<string>|null
     */
    #[Assert\NotNull]
    #[Assert\Type('array')]
    #[Assert\Choice(choices: [
        OrganizationPermission::ADMIN,
        OrganizationPermission::PROJECT_CREATE,
        OrganizationPermission::PROJECT_WRITE,
        OrganizationPermission::READ,
    ], multiple: true)]
    public ?array $permissions = null;
}
