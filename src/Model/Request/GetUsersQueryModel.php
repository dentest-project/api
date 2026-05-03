<?php

namespace App\Model\Request;

class GetUsersQueryModel extends AbstractQueryRequestModel
{
    public ?string $organization = null;

    public ?string $q = null;
}
