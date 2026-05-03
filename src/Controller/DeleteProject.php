<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Security\Voter\Verb;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects/{id}', requirements: ['id' => '[0-9a-f-]+'], methods: ['DELETE'])]
class DeleteProject extends Api
{
    public function __construct(
        private readonly ProjectRepository $projectRepository
    ) {}

    public function __invoke(Project $project): Response
    {
        $this->denyAccessUnlessGranted(Verb::DELETE, $project);

        try {
            $this->projectRepository->delete($project);

            return new Response();
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
