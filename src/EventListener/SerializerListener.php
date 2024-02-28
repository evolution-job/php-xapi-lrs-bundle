<?php

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use Xabbuh\XApi\Serializer\StateSerializerInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class SerializerListener
{

    public function __construct(
        private readonly StatementSerializerInterface $statementSerializer,
        private readonly StateSerializerInterface $stateSerializer
    ) { }

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        $request = $requestEvent->getRequest();

        if (!$request->attributes->has('xapi_lrs.route')) {
            return;
        }

        try {
            switch ($request->attributes->get('xapi_serializer')) {

                case 'state':
                    $request->attributes->set('state', $this->stateSerializer->deserializeState($request->query->all(), $request->getContent()));
                    break;
                case 'statement':
                    $request->attributes->set('statement', $this->statementSerializer->deserializeStatement($request->getContent()));
                    break;
            }
        } catch (ExceptionInterface $exception) {
            throw new BadRequestHttpException(sprintf('The content of the request cannot be deserialized into a valid xAPI %s.', $request->attributes->get('xapi_serializer')), $exception);
        }
    }
}
