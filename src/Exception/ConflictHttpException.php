<?php

declare(strict_types=1);

namespace XApi\LrsBundle\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException as SymfonyConflictHttpException;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class ConflictHttpException extends SymfonyConflictHttpException implements XApiExceptionInterface
{

}