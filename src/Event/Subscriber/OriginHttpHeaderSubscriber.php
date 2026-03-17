<?php

namespace App\Event\Subscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class OriginHttpHeaderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%env(ALLOWED_ORIGIN)%')]
        private string $allowedOrigins
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->getRequest()->getMethod() === Request::METHOD_OPTIONS) {
            $response = new Response();
            $response->headers->add([
                'Access-Control-Allow-Origin' => $this->allowedOrigins,
                'Access-Control-Allow-Methods' => implode(',', [
                    Request::METHOD_GET,
                    Request::METHOD_POST,
                    Request::METHOD_PUT,
                    Request::METHOD_DELETE
                ]),
                'Access-Control-Allow-Headers' => 'content-type,authorization'
            ]);
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $response->headers->add([
            'Access-Control-Allow-Origin' => $this->allowedOrigins
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => 'onKernelResponse'
        ];
    }
}
