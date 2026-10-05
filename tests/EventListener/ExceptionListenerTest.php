<?php

namespace XApi\LrsBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Common\Exception\ConflictException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use XApi\LrsBundle\App\XapiAttribute;
use XApi\LrsBundle\App\XapiHeader;
use XApi\LrsBundle\EventListener\ExceptionListener;
use XApi\LrsBundle\EventListener\XapiRequestMatcher;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class ExceptionListenerTest extends TestCase
{
    public function testPackageBadRequestExceptionBecomesABadRequestResponse(): void
    {
        $event = $this->createExceptionEvent(new BadRequestException('Invalid request'));

        new ExceptionListener(new XapiRequestMatcher())->onKernelException($event);

        self::assertSame(400, $event->getResponse()?->getStatusCode());
        self::assertSame('Invalid request', $event->getResponse()?->getContent());
    }

    public function testPackageConflictExceptionBecomesAConflictResponse(): void
    {
        $event = $this->createExceptionEvent(new ConflictException('Statement conflict'));

        new ExceptionListener(new XapiRequestMatcher())->onKernelException($event);

        self::assertSame(409, $event->getResponse()?->getStatusCode());
        self::assertSame('Statement conflict', $event->getResponse()?->getContent());
        self::assertSame('1.0.3', $event->getResponse()?->headers->get(XapiHeader::VERSION));
    }

    public function testPackageNotFoundExceptionBecomesANotFoundResponse(): void
    {
        $event = $this->createExceptionEvent(new NotFoundException('Statement not found'));

        new ExceptionListener(new XapiRequestMatcher())->onKernelException($event);

        self::assertSame(404, $event->getResponse()?->getStatusCode());
        self::assertSame('Statement not found', $event->getResponse()?->getContent());
    }

    private function createExceptionEvent(\Throwable $throwable): ExceptionEvent
    {
        $request = new Request();
        $request->attributes->set(XapiAttribute::LRS_ROUTE, true);
        $request->attributes->set('_route', 'xapi_lrs.statement.get');

        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $throwable
        );
    }
}
