<?php

declare(strict_types=1);

namespace XApi\LrsBundle\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException as SymfonyBadRequestHttpException;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class BadRequestHttpException extends SymfonyBadRequestHttpException implements XApiExceptionInterface
{

}