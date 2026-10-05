<?php

namespace XApi\LrsBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Xabbuh\XApi\DataFixtures\StateFixtures;
use XApi\LrsBundle\Controller\StatePutController;
use XApi\LrsBundle\Response\StateDocumentResponse;
use XApi\Repository\Api\StateRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class StatePutControllerTest extends TestCase
{
    public function testPutAllowsStateWithoutConcurrencyHeaders(): void
    {
        $state = StateFixtures::getTypicalState();
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->never())->method('findState');
        $repository->expects($this->once())->method('storeState')->with($state);

        $response = new StatePutController($repository)->putState($state, new Request());

        self::assertSame(204, $response->getStatusCode());
    }

    public function testPutAcceptsMatchingIfMatchHeader(): void
    {
        $state = StateFixtures::getTypicalState();
        $etag = (new StateDocumentResponse($state->getData(), contentType: $state->getContentType()))->headers->get('ETag');
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->once())->method('findState')->with($state)->willReturn($state);
        $repository->expects($this->once())->method('storeState')->with($state);

        $response = new StatePutController($repository)->putState(
            $state,
            new Request(server: ['HTTP_IF_MATCH' => $etag])
        );

        self::assertSame(204, $response->getStatusCode());
    }

    public function testPutRejectsStaleIfMatchHeaderWithoutStoring(): void
    {
        $state = StateFixtures::getTypicalState();
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->once())->method('findState')->with($state)->willReturn($state);
        $repository->expects($this->never())->method('storeState');

        try {
            new StatePutController($repository)->putState(
                $state,
                new Request(server: ['HTTP_IF_MATCH' => '"stale"'])
            );
            self::fail('Expected stale If-Match to fail.');
        } catch (PreconditionFailedHttpException $exception) {
            self::assertSame(412, $exception->getStatusCode());
        }
    }

    public function testPutRejectsIfNoneMatchWildcardWhenStateExists(): void
    {
        $state = StateFixtures::getTypicalState();
        $repository = $this->createMock(StateRepositoryInterface::class);
        $repository->expects($this->once())->method('findState')->with($state)->willReturn($state);
        $repository->expects($this->never())->method('storeState');

        try {
            new StatePutController($repository)->putState(
                $state,
                new Request(server: ['HTTP_IF_NONE_MATCH' => '*'])
            );
            self::fail('Expected If-None-Match wildcard to fail.');
        } catch (PreconditionFailedHttpException $exception) {
            self::assertSame(412, $exception->getStatusCode());
        }
    }
}
