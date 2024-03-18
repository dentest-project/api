<?php

namespace App\Controller;

use App\Entity\Feature;
use App\Repository\FeatureRepository;
use App\Security\Voter\Verb;
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
        private readonly FeatureRepository $featureRepository
    ) {}

    public function __invoke(#[EntityArgument] Feature $feature): Response
    {
        $this->denyAccessUnlessGranted(Verb::UPDATE, $feature);

        $this->validate($feature);

        try {
            $this->featureRepository->save($feature);

            return $this->buildSerializedResponse($feature, 'READ_FEATURE');
        } catch (ORMException | OptimisticLockException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        } catch (UniqueConstraintViolationException $e) {
            throw new ConflictHttpException();
        }
    }
}
