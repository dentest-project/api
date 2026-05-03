<?php

namespace App\Controller;

use App\Entity\Path;
use App\Repository\PathRepository;
use App\Security\Voter\Verb;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/paths/{id}', requirements: ['id' => '[0-9a-f-]+'], methods: ['DELETE'])]
class DeletePath extends Api
{
    public function __construct(
        private readonly PathRepository $pathRepository
    ) {}

    public function __invoke(Path $path): Response
    {
        $this->denyAccessUnlessGranted(Verb::DELETE, $path);

        try {
            $this->pathRepository->delete($path);

            return new Response();
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
