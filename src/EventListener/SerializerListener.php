<?php

namespace XApi\LrsBundle\EventListener;

use JsonException;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Xabbuh\XApi\Common\Exception\UnsupportedStatementVersionException;
use Xabbuh\XApi\Serializer\Exception\DeserializationException;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use Xabbuh\XApi\Serializer\StateSerializerInterface;

/**
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
readonly class SerializerListener
{
    public function __construct(
        private StatementSerializerInterface $statementSerializer,
        private StateSerializerInterface $stateSerializer
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

                    $parameters = [];
                    foreach ($request->query->all() as $key => $value) {
                        if (is_string($value) && str_starts_with($value, '{') && str_ends_with($value, '}')) {
                            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                        }
                        $parameters[$key] = $value;
                    }

                    $jsonEncodeParameters = json_encode($parameters, JSON_THROW_ON_ERROR);

                    $request->attributes->set('state', $this->stateSerializer->deserializeState($jsonEncodeParameters, $request->getContent() ?? ''));

                    break;

                case 'statement':

                    $request->attributes->set('statement', $this->statementSerializer->deserializeStatement($request->getContent() ?? ''));

                    break;
            }
        } catch (UnsupportedStatementVersionException|InvalidArgumentException|DeserializationException|JsonException $unsupportedStatementVersionException) {
            throw new BadRequestHttpException(sprintf('The content of the request cannot be deserialized into a valid xAPI %s.', $request->attributes->get('xapi_serializer')), $unsupportedStatementVersionException);
        }
    }
}
