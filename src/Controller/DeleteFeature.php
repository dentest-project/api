<?php

namespace App\Controller;

use App\Entity\Feature;
use App\Repository\FeatureRepository;
use App\Security\Voter\Verb;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/features/{id}', requirements: ['id' => '[0-9a-f-]+'], methods: ['DELETE'])]
class DeleteFeature extends Api
{
    public function __construct(
        private readonly FeatureRepository $featureRepository
    ) {}

    public function __invoke(Feature $feature): Response
    {
        $this->denyAccessUnlessGranted(Verb::DELETE, $feature);

        try {
            $this->featureRepository->delete($feature);

            return new Response();
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }
    }
}
