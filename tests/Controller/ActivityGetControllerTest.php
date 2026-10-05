<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\DataFixtures\ActivityFixtures;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Serializer\ActivitySerializerInterface;
use XApi\LrsBundle\Controller\ActivityGetController;
use XApi\LrsBundle\Response\JsonResponse;
use XApi\Repository\Api\ActivityRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class ActivityGetControllerTest extends TestCase
{
    public function testGetActivityAcceptsAbsoluteIri(): void
    {
        $activityId = 'https://example.org/activity';
        $activity = ActivityFixtures::getTypicalActivity();
        $repository = $this->createMock(ActivityRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findActivityById')
            ->with(self::callback(static fn (IRI $iri): bool => $iri->getValue() === $activityId))
            ->willReturn($activity);
        $serializer = $this->createMock(ActivitySerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serializeActivity')
            ->with($activity)
            ->willReturn('{"id":"https://example.org/activity"}');

        $response = new ActivityGetController($repository, $serializer)
            ->getActivities(new Request(['activityId' => $activityId]));

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testGetActivityRejectsMalformedIri(): void
    {
        $repository = $this->createMock(ActivityRepositoryInterface::class);
        $repository->expects($this->never())->method('findActivityById');
        $controller = new ActivityGetController($repository, $this->createStub(ActivitySerializerInterface::class));

        try {
            $controller->getActivities(new Request(['activityId' => 'not an IRI']));
            self::fail('Expected malformed activityId to be rejected.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getCode());
        }
    }

    public function testGetActivityRejectsMalformedPercentEscape(): void
    {
        $repository = $this->createMock(ActivityRepositoryInterface::class);
        $repository->expects($this->never())->method('findActivityById');
        $controller = new ActivityGetController($repository, $this->createStub(ActivitySerializerInterface::class));

        try {
            $controller->getActivities(new Request(['activityId' => 'https://example.org/%zz']));
            self::fail('Expected malformed percent escape to be rejected.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getCode());
        }
    }

    public function testGetActivityRejectsUnknownQueryParameters(): void
    {
        $repository = $this->createMock(ActivityRepositoryInterface::class);
        $repository->expects($this->never())->method('findActivityById');
        $controller = new ActivityGetController($repository, $this->createStub(ActivitySerializerInterface::class));

        try {
            $controller->getActivities(new Request(['activityId' => 'https://example.org/activity', 'extra' => 'value']));
            self::fail('Expected unrecognized query parameter to be rejected.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getCode());
        }
    }

    public function testGetUnknownActivityReturnsActivityWithRequestedId(): void
    {
        $activityId = 'https://example.org/activity';
        $repository = $this->createMock(ActivityRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findActivityById')
            ->with(self::callback(static fn (IRI $iri): bool => $iri->getValue() === $activityId))
            ->willReturn(null);
        $serializer = $this->createMock(ActivitySerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serializeActivity')
            ->with(self::callback(static fn (Activity $activity): bool => $activity->getId()->getValue() === $activityId))
            ->willReturn('{"id":"https://example.org/activity"}');

        $response = new ActivityGetController($repository, $serializer)
            ->getActivities(new Request(['activityId' => $activityId]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"id":"https://example.org/activity"}', $response->getContent());
    }
}
