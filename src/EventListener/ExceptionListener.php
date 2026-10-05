<?php

declare(strict_types=1);

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\EventListener;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Xabbuh\XApi\Common\Exception\XApiException;
use XApi\LrsBundle\App\XapiHeader;
use XApi\LrsBundle\App\XapiVersion;

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
        $throwable = $exceptionEvent->getThrowable();
        $isXapiRequest = $this->xapiRequestMatcher->matches($exceptionEvent);

        if (!$throwable instanceof XApiException && !$isXapiRequest) {

            return;
        }

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'Internal Server Error';

        if ($throwable instanceof HttpExceptionInterface) {
            $statusCode = $throwable->getStatusCode();
            $message = $throwable->getMessage();
        } elseif ($throwable instanceof XApiException) {
            if (Response::HTTP_BAD_REQUEST <= $throwable->getCode() && 600 > $throwable->getCode()) {
                $statusCode = $throwable->getCode();
            }

            $message = $throwable->getMessage();
        }

        $response = new Response($message, $statusCode, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);

        // Add X-Experience-API-Version
        $response->headers->set(XapiHeader::VERSION, XapiVersion::V1_0_3);
        if ($isXapiRequest) {
            $response->headers->set(
                XapiHeader::CONSISTENT_THROUGH_HEADER,
                new DateTimeImmutable()->format(XapiHeader::DATE_FORMAT)
            );
        }

        $exceptionEvent->setResponse($response);
    }
}
