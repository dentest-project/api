<?php

namespace App\Controller;

use App\Entity\DomainAssociation;
use App\Entity\DomainEntity;
use App\Event\MailEvent;
use App\Mail\MailInterface;
use App\Serializer\Groups;
use App\Serializer\Normalizer\FeatureNormalizer;
use App\Serializer\Normalizer\OrganizationNormalizer;
use App\Serializer\Normalizer\PathNormalizer;
use App\Serializer\Normalizer\ProjectNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
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
    protected EventDispatcherInterface $dispatcher;

    protected SerializerInterface $serializer;

    protected ValidatorInterface $validator;

    protected function buildSerializedResponse($data, Groups $group = null, int $statusCode = Response::HTTP_OK): Response
    {
        $context = [
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            AbstractObjectNormalizer::CIRCULAR_REFERENCE_HANDLER => fn (object $object, ?string $format = null, array $context = []): mixed => $this->limitSerializedValue($object),
            AbstractObjectNormalizer::MAX_DEPTH_HANDLER => fn (mixed $value, object $object, string $attributeName, ?string $format = null, array $context = []): mixed => $this->limitSerializedValue($value),
        ];

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

    private function limitSerializedValue(mixed $value): mixed
    {
        if ($value instanceof DomainEntity) {
            return [
                'id' => $value->id ?? null,
                'name' => $value->name ?? null,
            ];
        }

        if ($value instanceof DomainAssociation) {
            return [
                'id' => $value->id ?? null,
                'sourceName' => $value->sourceName ?? null,
                'targetName' => $value->targetName ?? null,
            ];
        }

        if (is_iterable($value)) {
            $limitedValues = [];

            foreach ($value as $key => $item) {
                $limitedValues[$key] = $this->limitSerializedValue($item);
            }

            return $limitedValues;
        }

        return $value;
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

    protected function sendMail(string $to, MailInterface $mail): void
    {
        $this
            ->dispatcher
            ->addListener(
                KernelEvents::TERMINATE,
                fn () => $this->dispatcher->dispatch(new MailEvent($to, $mail), MailEvent::NAME)
            );
    }

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    #[Required]
    public function setSerializer(
        FeatureNormalizer $featureNormalizer,
        OrganizationNormalizer $organizationNormalizer,
        PathNormalizer $pathNormalizer,
        ProjectNormalizer $projectNormalizer
    ): void {
        $this->serializer = new Serializer([
            new BackedEnumNormalizer(),
            new UidNormalizer(),
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
