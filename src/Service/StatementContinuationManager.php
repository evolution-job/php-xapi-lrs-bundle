<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Service;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Router;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\IRL;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
final readonly class StatementContinuationManager
{
    private const string CACHE_PREFIX = 'xapi_more_';

    private const int CACHE_TTL = 86400;

    public function __construct(
        private CacheInterface $cache,
        private Router $router
    ) { }

    /**
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function resolveParameters(Request $request): array
    {
        $parameters = $request->query->all();

        if (!array_key_exists('moreId', $parameters)) {
            return $parameters;
        }

        if (1 !== count($parameters) || !is_string($parameters['moreId']) || !preg_match('/\A[a-f0-9]{64}\z/', $parameters['moreId'])) {
            throw new BadRequestException('The moreId parameter must be a valid statement continuation token and used by itself.');
        }

        $cachedParameters = $this->cache->get(self::CACHE_PREFIX.$parameters['moreId'], static function (ItemInterface $item): never {
            throw new NotFoundException('The statement continuation link is invalid or has expired.');
        });

        if (!is_array($cachedParameters)) {
            throw new NotFoundException('The statement continuation link is invalid or has expired.');
        }

        return $cachedParameters;
    }

    /**
     * @param array<string, mixed> $queryParameters
     * @throws InvalidArgumentException
     */
    public function createMoreUrl(array $queryParameters, int $limit, int $offset): IRL
    {
        $nextParameters = array_merge($queryParameters, [
            'limit' => $limit,
            'offset' => $offset + $limit,
        ]);
        $moreId = bin2hex(random_bytes(32));

        $this->cache->get(self::CACHE_PREFIX.$moreId, static function (ItemInterface $item) use ($nextParameters): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $nextParameters;
        });

        return IRL::fromString($this->router->generate('xapi_lrs.statement.get', ['moreId' => $moreId]));
    }
}
