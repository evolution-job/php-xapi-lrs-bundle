<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\DataFixtures\StateFixtures;
use Xabbuh\XApi\Model\State;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use Xabbuh\XApi\Serializer\StateSerializerInterface;
use XApi\LrsBundle\EventListener\SerializerListener;
use XApi\LrsBundle\Service\MultipartStatementParser;
use XApi\LrsBundle\Service\RequestDeserializer;
use XApi\LrsBundle\Service\RequestMatcher;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class SerializerListenerTest extends TestCase
{
    private StatementSerializerInterface $statementSerializer;
    private StateSerializerInterface $stateSerializer;
    private SerializerListener $listener;

    protected function setUp(): void
    {
        $this->statementSerializer = $this->createStub(StatementSerializerInterface::class);
        $this->stateSerializer = $this->createStub(StateSerializerInterface::class);
        $this->listener = $this->createListener();
    }

    public function testOnKernelRequestWithSingleStatement(): void
    {
        $jsonContent = '{"id": "eaf1c3e2-be78-434a-ab70-4790b07f4c64"}';

        $this->statementSerializer = $this->createMock(StatementSerializerInterface::class);
        $statementInstance = new Statement();

        $this->statementSerializer
            ->expects($this->once())
            ->method('deserializeStatement')
            ->with($jsonContent)
            ->willReturn($statementInstance);

        $this->listener = $this->createListener();
        $request = new Request([], [], [
            'xapi_lrs.route' => true,
            '_route' => 'xapi_lrs.statement.post',
            'xapi_serializer' => 'statement',
        ], [], [], [], $jsonContent);
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $this->assertTrue($request->attributes->has('statement'));
        $this->assertFalse($request->attributes->has('statements'));
        $this->assertSame($statementInstance, $request->attributes->get('statement'));
    }

    public function testOnKernelRequestWithCollectionOfStatements(): void
    {
        $jsonContent = '[{"id": "eaf1c3e2-be78-434a-ab70-4790b07f4c64"}]';

        $this->statementSerializer = $this->createMock(StatementSerializerInterface::class);
        $statementsArray = [new Statement()];

        $this->statementSerializer
            ->expects($this->once())
            ->method('deserializeStatements')
            ->with($jsonContent)
            ->willReturn($statementsArray);

        $this->statementSerializer
            ->expects($this->never())
            ->method('deserializeStatement');

        $this->listener = $this->createListener();
        $request = new Request([], [], [
            'xapi_lrs.route' => true,
            '_route' => 'xapi_lrs.statement.post',
            '_controller' => 'XApi\LrsBundle\Controller\StatementPostController::postStatement',
            'xapi_serializer' => 'statement',
        ], [], [], [], $jsonContent);
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $this->assertTrue($request->attributes->has('statements'));
        $this->assertFalse($request->attributes->has('statement'));
        $this->assertSame($statementsArray, $request->attributes->get('statements'));
        $this->assertSame(
            'XApi\LrsBundle\Controller\StatementPostController::postStatements',
            $request->attributes->get('_controller')
        );
    }

    public function testOnKernelRequestAllowsMissingStateIdForGetAndDelete(): void
    {
        $this->stateSerializer = $this->createMock(StateSerializerInterface::class);
        $state = $this->stateWithoutId();
        $this->stateSerializer
            ->expects($this->exactly(2))
            ->method('deserializeState')
            ->willReturn($state);
        $this->listener = $this->createListener();

        foreach ([Request::METHOD_GET, Request::METHOD_DELETE] as $method) {
            $request = $this->createStateRequest($method);
            $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

            $this->listener->onKernelRequest($event);

            $this->assertSame($state, $request->attributes->get('state'));
        }
    }

    public function testOnKernelRequestRejectsMissingStateIdForPostAndPut(): void
    {
        $this->stateSerializer = $this->createMock(StateSerializerInterface::class);
        $state = $this->stateWithoutId();
        $this->stateSerializer
            ->expects($this->exactly(2))
            ->method('deserializeState')
            ->willReturn($state);
        $this->listener = $this->createListener();

        foreach ([Request::METHOD_POST, Request::METHOD_PUT] as $method) {
            $request = $this->createStateRequest($method);
            $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

            try {
                $this->listener->onKernelRequest($event);
                self::fail('Expected a missing stateId to be rejected.');
            } catch (BadRequestException) {
                self::assertFalse($request->attributes->has('state'));
            }
        }
    }

    public function testOnKernelRequestRejectsUnknownStateQueryParameters(): void
    {
        $this->stateSerializer = $this->createMock(StateSerializerInterface::class);
        $this->stateSerializer->expects($this->never())->method('deserializeState');
        $this->listener = $this->createListener();

        $request = $this->createStateRequest(Request::METHOD_GET);
        $request->query->set('unexpected', 'value');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        try {
            $this->listener->onKernelRequest($event);
            self::fail('Expected an unknown State query parameter to be rejected.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getCode());
        }
    }

    public function testOnKernelRequestPreservesNonJsonStateDocumentBody(): void
    {
        $this->stateSerializer = $this->createMock(StateSerializerInterface::class);
        $state = StateFixtures::getMinimalState();
        $this->stateSerializer
            ->expects($this->once())
            ->method('deserializeState')
            ->willReturn($state);
        $this->listener = $this->createListener();

        $request = $this->createStateRequest(Request::METHOD_POST, 'plain document', 'text/plain');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $this->assertSame('plain document', $request->attributes->get('state')->getData());
        $this->assertSame('text/plain', $request->attributes->get('state')->getContentType());
    }

    public function testOnKernelRequestPreservesJsonStateDocumentContentType(): void
    {
        $this->stateSerializer = $this->createMock(StateSerializerInterface::class);
        $state = StateFixtures::getMinimalState();
        $this->stateSerializer
            ->expects($this->once())
            ->method('deserializeState')
            ->willReturn($state);
        $this->listener = $this->createListener();

        $request = $this->createStateRequest(
            Request::METHOD_POST,
            '{"progress": 1}',
            'application/json; charset=utf-8'
        );
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        self::assertSame('application/json; charset=utf-8', $request->attributes->get('state')->getContentType());
    }

    private function stateWithoutId(): State
    {
        $state = StateFixtures::getMinimalState();

        return new State($state->getActivity(), $state->getAgent(), null);
    }

    private function createListener(): SerializerListener
    {
        return new SerializerListener(
            new RequestDeserializer($this->statementSerializer, $this->stateSerializer, new MultipartStatementParser()),
            new RequestMatcher()
        );
    }

    private function createStateRequest(string $method, string $content = '', string $contentType = ''): Request
    {
        $route = match ($method) {
            Request::METHOD_POST => 'xapi_lrs.state.post',
            Request::METHOD_PUT => 'xapi_lrs.state.put',
            Request::METHOD_DELETE => 'xapi_lrs.state.delete',
            default => 'xapi_lrs.state.get',
        };

        return new Request(
            [
                'activityId' => 'https://example.com/activity',
                'agent' => '{"mbox":"mailto:learner@example.com"}',
            ],
            [],
            [
                'xapi_lrs.route' => true,
                '_route' => $route,
                'xapi_serializer' => 'state',
            ],
            [],
            [],
            ['REQUEST_METHOD' => $method, 'CONTENT_TYPE' => $contentType],
            $content
        );
    }
}
