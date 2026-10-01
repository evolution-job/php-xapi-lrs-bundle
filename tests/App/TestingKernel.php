<?php

namespace XApi\LrsBundle\Tests\App;

use Exception;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Xabbuh\XApi\Common\Exception\NotFoundException;
use Xabbuh\XApi\Model\Activity;
use Xabbuh\XApi\Model\Actor;
use Xabbuh\XApi\Model\IRI;
use Xabbuh\XApi\Model\State;
use Xabbuh\XApi\Model\Statement;
use Xabbuh\XApi\Model\StatementId;
use Xabbuh\XApi\Model\StatementsFilter;
use XApi\LrsBundle\XApiLrsBundle;
use XApi\Repository\Api\ActivityRepositoryInterface;
use XApi\Repository\Api\StatementRepositoryInterface;
use XApi\Repository\Api\StateRepositoryInterface;

class FakeActivityRepository implements ActivityRepositoryInterface
{
    public function findActivityById(IRI $iri): ?Activity { }
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

    public function findState(State $state): ?State { }

    public function findStates(State $state): array { }

    public function removeState(State $state, bool $flush = true): void { }

    public function storeState(State $state, bool $flush = true): void { }
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

            $container->register('xapi_lrs.repository.state', FakeStateRepository::class)->setPublic(true);
            $container->register('xapi_lrs.repository.statement', FakeStatementRepository::class)->setPublic(true);
            $container->register('xapi_lrs.repository.activity', FakeActivityRepository::class)->setPublic(true);
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
