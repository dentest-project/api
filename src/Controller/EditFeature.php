<?php

namespace App\Controller;

use App\Entity\Feature;
use App\FeatureSummary\FeatureSummaryQueue;
use App\Repository\FeatureRepository;
use App\Security\Voter\Verb;
use App\Serializer\Groups;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use RollandRock\ParamConverterBundle\Attribute\EntityArgument;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/features', methods: ['PUT'])]
class EditFeature extends Api
{
    public function __construct(
        private readonly FeatureRepository $featureRepository,
        private readonly FeatureSummaryQueue $featureSummaryQueue
    ) {}

    public function __invoke(#[EntityArgument] Feature $feature): Response
    {
        $this->denyAccessUnlessGranted(Verb::UPDATE, $feature);

        $this->validate($feature);

        $previousStatus = $feature->id ? $this->featureRepository->findStatusById($feature->id) : null;
        $shouldGenerateSummary = $previousStatus === Feature::FEATURE_STATUS_DRAFT
            && $feature->status === Feature::FEATURE_STATUS_READY_TO_DEV;

        try {
            $this->featureRepository->save($feature);
            if ($shouldGenerateSummary && $feature->id !== null) {
                $this->featureSummaryQueue->enqueue($feature->id);
            }

            return $this->buildSerializedResponse($feature, Groups::ReadFeature);
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        } catch (UniqueConstraintViolationException $e) {
            throw new ConflictHttpException();
        }
    }
}
