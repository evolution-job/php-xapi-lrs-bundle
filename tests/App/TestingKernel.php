<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Tests\App;

use DateTimeImmutable;
use Exception;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\Actor;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Model\ProfileDocument;
use Xabbuh\XApi\Model\State;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementsFilter;
use Xabbuh\XApi\Model\Verb;
use XApi\LrsBundle\XApiLrsBundle;
use XApi\Repository\Api\ActivityRepositoryInterface;
use XApi\Repository\Api\ProfileRepositoryInterface;
use XApi\Repository\Api\StatementRepositoryInterface;
use XApi\Repository\Api\StateRepositoryInterface;
use XApi\Repository\Api\VerbRepositoryInterface;

/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class FakeActivityRepository implements ActivityRepositoryInterface
{
    public function findActivityById(IRI $iri): ?Activity { return null; }
}

class FakeProfileRepository implements ProfileRepositoryInterface
{
    /** @var array<string, array<string, ProfileDocument>> */
    private array $documents = [];

    public function find(string $resource, string $profileId): ?ProfileDocument
    {
        return $this->documents[$resource][$profileId] ?? null;
    }

    public function findIds(string $resource, ?DateTimeImmutable $since = null): array
    {
        $documents = $this->documents[$resource] ?? [];

        return array_keys(array_filter(
            $documents,
            static fn (ProfileDocument $profileDocument): bool => !$since instanceof DateTimeImmutable || $profileDocument->updated > $since
        ));
    }

    public function store(string $resource, string $profileId, ProfileDocument $profileDocument): void
    {
        $this->documents[$resource][$profileId] = $profileDocument;
    }

    public function remove(string $resource, ?string $profileId = null): void
    {
        if (null === $profileId) {
            unset($this->documents[$resource]);

            return;
        }

        unset($this->documents[$resource][$profileId]);
    }
}

class FakeStatementRepository implements StatementRepositoryInterface
{
    public function findStatementById(StatementId $statementId, ?Actor $actor = null): Statement { throw new NotFoundException('Not found'); }

    public function findStatementsBy(StatementsFilter $statementsFilter, ?Actor $actor = null): array { return []; }

    public function storeStatement(Statement $statement, bool $flush = true): StatementId { return StatementId::fromString('eaf1c3e2-be78-434a-ab70-4790b07f4c64'); }

    public function findVoidedStatementById(StatementId $voidedStatementId, ?Actor $actor = null): Statement { throw new NotFoundException('Not found'); }
}

class FakeStateRepository implements StateRepositoryInterface
{

    public function findState(State $state): ?State { return null; }

    public function findStates(State $state, ?DateTimeImmutable $since = null): array { return []; }

    public function removeState(State $state, bool $flush = true): void { }

    public function storeState(State $state, bool $flush = true): void { }
}

class FakeVerbRepository implements VerbRepositoryInterface
{
    public function findVerbById(IRI $iri): ?Verb { throw new NotFoundException('Not found'); }
}


class TestingKernel extends Kernel
{
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new XApiLrsBundle(),
        ];
    }

    /**
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secret' => 'F00',
                'test'   => true,
                'router' => [
                    'resource' => __DIR__.'/../../src/Resources/config/routing.yaml',
                    'type'     => 'yaml',
                ],
            ]);

            $container->loadFromExtension('xapi_lrs', [
                'type'                   => 'in_memory',
                'object_manager_service' => 'doctrine.orm.entity_manager',
                'allowed_origins'        => ['https://lrs.example.com', 'https://learning.repository.example.com'],
            ]);

            $container->register(ActivityRepositoryInterface::class, FakeActivityRepository::class)->setPublic(true);
            $container->register(ProfileRepositoryInterface::class, FakeProfileRepository::class)->setPublic(true);
            $container->register(StateRepositoryInterface::class, FakeStateRepository::class)->setPublic(true);
            $container->register(StatementRepositoryInterface::class, FakeStatementRepository::class)->setPublic(true);
            $container->register(VerbRepositoryInterface::class, FakeVerbRepository::class)->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/XApiLrsBundle/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/XApiLrsBundle/logs';
    }
}
