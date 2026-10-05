<?php

declare(strict_types=1);

namespace XApi\LrsBundle\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException as SymfonyNotFoundHttpException;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class NotFoundHttpException extends SymfonyNotFoundHttpException implements XApiExceptionInterface
{

}