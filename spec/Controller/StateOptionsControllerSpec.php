<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\XApi\LrsBundle\Controller;

use DateTime;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\DataFixtures\StatementFixtures;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementResult;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Serializer\StatementResultSerializerInterface;
use Xabbuh\XApi\Serializer\StatementSerializerInterface;
use XApi\Fixtures\Json\StatementJsonFixtures;
use XApi\Fixtures\Json\StatementResultJsonFixtures;
use XApi\LrsBundle\Model\StatementsFilterFactory;
use XApi\LrsBundle\Response\MultipartResponse;
use XApi\Repository\Api\StatementRepositoryInterface;

class StateOptionsControllerSpec extends ObjectBehavior
{
    public function it_should_throws_a_BadRequestHttpException_if_an_stateId_is_not_part_of_a_get_request(): void
    {
        $request = new Request();

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('optionsState', [$request]);
    }

    public function it_should_throws_a_BadRequestHttpException_if_an_stateId_is_a_string(): void
    {
        $request = new Request();
        $request->query->set('stateId', []);

        $this
            ->shouldThrow(BadRequestHttpException::class)
            ->during('optionsState', [$request]);
    }
}
