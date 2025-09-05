<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Response;

use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class XapiJsonResponse extends JsonResponse
{
    public function __construct(mixed $data = null, int $status = 200, array $headers = [], bool $json = false)
    {
        Parent::__construct($data, $status, $headers, $json);

        $dateTime = new DateTime();
        $this->headers->set('X-Experience-API-Consistent-Through', $dateTime->format('Y-m-d\TH:i:sP'));
    }
}