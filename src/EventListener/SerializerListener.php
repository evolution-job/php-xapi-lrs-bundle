<?php

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use Xabbuh\XApi\Serializer\StateSerializerInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
class SerializerListener
{
    private $statementSerializer;
    private $stateSerializer;

    public function __construct(
        StatementSerializerInterface $statementSerializer,
        StateSerializerInterface $stateSerializer
    ) {
        $this->statementSerializer = $statementSerializer;
        $this->stateSerializer = $stateSerializer;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

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
        } catch (ExceptionInterface $e) {
            throw new BadRequestHttpException(sprintf('The content of the request cannot be deserialized into a valid xAPI %s.', $request->attributes->get('xapi_serializer')), $e);
        }
    }
}
