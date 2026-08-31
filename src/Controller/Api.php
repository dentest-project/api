<?php

namespace App\Controller;

use App\Serializer\Groups;
use App\Serializer\Normalizer\DomainAssociationNormalizer;
use App\Serializer\Normalizer\FeatureNormalizer;
use App\Serializer\Normalizer\OrganizationNormalizer;
use App\Serializer\Normalizer\PathNormalizer;
use App\Serializer\Normalizer\ProjectNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class Api extends AbstractController
{
    protected SerializerInterface $serializer;

    protected ValidatorInterface $validator;

    protected function buildSerializedResponse($data, Groups $group = null, int $statusCode = Response::HTTP_OK): Response
    {
        $context = [AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true];

        return new Response(
            $this->serializer->serialize(
                $data,
                'json',
                [
                    ...$context,
                    ...($group ? ['groups' => [$group->value]] : [])
                ]
            ),
            $statusCode,
            [
                'Content-type' => 'application/json'
            ]
        );
    }

    /**
     * @throws BadRequestHttpException
     */
    protected function validate(object $entity): void
    {
        $errors = $this->validator->validate($entity);

        if ($errors->count() > 0) {
            throw new BadRequestHttpException();
        }
    }

    #[Required]
    public function setSerializer(
        DomainAssociationNormalizer $domainAssociationNormalizer,
        FeatureNormalizer $featureNormalizer,
        OrganizationNormalizer $organizationNormalizer,
        PathNormalizer $pathNormalizer,
        ProjectNormalizer $projectNormalizer
    ): void {
        $this->serializer = new Serializer([
            new BackedEnumNormalizer(),
            new UidNormalizer(),
            $domainAssociationNormalizer,
            $featureNormalizer,
            $organizationNormalizer,
            $pathNormalizer,
            $projectNormalizer,
            new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader())),
        ], [
            new JsonEncoder()
        ]);
    }

    #[Required]
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }
}
