<?php

namespace XApi\LrsBundle\EventListener;

use JsonException;
use Symfony\Component\HttpFoundation\Request;
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
final readonly class SerializerListener
{
    public function __construct(
        private StatementSerializerInterface $statementSerializer,
        private StateSerializerInterface $stateSerializer,
        private XapiRequestMatcher $xapiRequestMatcher
    ) { }

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        if (false === $this->xapiRequestMatcher->matches($requestEvent)) {
            return;
        }

        $request = $requestEvent->getRequest();

        if (true === $request->isMethod(Request::METHOD_OPTIONS)) {
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

                    $content = $request->getContent() ?? '';
                    $trimmedContent = ltrim($content);

                    if (str_starts_with($trimmedContent, '[')) {
                        // Collection of Statements
                        $statements = $this->statementSerializer->deserializeStatements($content);
                        $request->attributes->set('statements', $statements);
                        $controller = $request->attributes->get('_controller');

                        if (is_string($controller) && str_ends_with($controller, '::postStatements')) {
                            $request->attributes->set('_controller', str_replace('::postStatements', '::postStatementss', $controller));
                        }

                    } else {
                        // Statement Object
                        $request->attributes->set('statement', $this->statementSerializer->deserializeStatement($content));
                    }
                    break;
            }
        } catch (UnsupportedStatementVersionException|InvalidArgumentException|DeserializationException|JsonException $unsupportedStatementVersionException) {

            throw new BadRequestHttpException(
                sprintf(
                    'The content of the request cannot be deserialized into a valid xAPI %s.',
                    $request->attributes->get('xapi_serializer')
                ),
                $unsupportedStatementVersionException
            );
        }
    }
}
