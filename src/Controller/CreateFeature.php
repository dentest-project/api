<?php

namespace App\Controller;

use App\Entity\Feature;
use App\Repository\FeatureRepository;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use App\SummaryGeneration\SummaryQueuing\SummaryUpdateScheduler;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use RollandRock\ParamConverterBundle\Attribute\EntityArgument;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/features', methods: ['POST'])]
class CreateFeature extends Api
{
    public function __construct(
        private readonly FeatureRepository $featureRepository,
        private readonly SummaryUpdateScheduler $summaryUpdateScheduler
    ) {}

    public function __invoke(#[EntityArgument] Feature $feature): Response
    {
        $this->denyAccessUnlessGranted(Verb::CREATE, $feature);

        $this->validate($feature);

        try {
            $this->featureRepository->save($feature);
            $this->summaryUpdateScheduler->scheduleFeatureUpdates($feature, null);

            return $this->buildSerializedResponse($feature, Groups::ReadFeature);
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        } catch (UniqueConstraintViolationException $e) {
            throw new ConflictHttpException();
        }
    }
}
