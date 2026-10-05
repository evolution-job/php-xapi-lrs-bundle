<?php

declare(strict_types=1);

namespace XApi\LrsBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use XApi\LrsBundle\App\XapiHeader;
use XApi\LrsBundle\App\XapiVersion;
use XApi\LrsBundle\Exception\XapiExceptionInterface;

/**
 * Converts Experience API specific domain exceptions into proper HTTP responses.
 *
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class ExceptionListener
{
    public function __construct(private XapiRequestMatcher $xapiRequestMatcher) { }

    public function onKernelException(ExceptionEvent $exceptionEvent): void
    {
        $exception = $exceptionEvent->getThrowable();

        if (!$exception instanceof XapiExceptionInterface
            && !$this->xapiRequestMatcher->matches($exceptionEvent)
        ) {
            return;
        }

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'Internal Server Error';

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        }

        $response = new Response($message, $statusCode, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);

        // Add X-Experience-API-Version
        $response->headers->set(XapiHeader::VERSION, XapiVersion::V1_0_3);

        $exceptionEvent->setResponse($response);
    }
}
