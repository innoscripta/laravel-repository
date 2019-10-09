<?php

namespace Orkhanahmadov\EloquentRepository;

use Illuminate\Contracts\Cache\Factory as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Orkhanahmadov\EloquentRepository\Repository\Concerns\CreatesEntity;
use Orkhanahmadov\EloquentRepository\Repository\Concerns\DeletesEntity;
use Orkhanahmadov\EloquentRepository\Repository\Concerns\SelectsEntity;
use Orkhanahmadov\EloquentRepository\Repository\Concerns\UpdatesEntity;
use Orkhanahmadov\EloquentRepository\Repository\Contracts\Repository;
use Orkhanahmadov\EloquentRepository\Repository\Criteria;

class EloquentRepository implements Repository
{
    use SelectsEntity;
    use CreatesEntity;
    use UpdatesEntity;
    use DeletesEntity;

    /**
     * @var Application
     */
    private $application;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var Cache
     */
    protected $cache;
    /**
     * @var string|null
     */
    protected $entity = null;
    /**
     * @var Builder|Model
     */
    protected $modelInstance;
    /**
     * @var string|null
     */
    protected $relation = null;

    /**
     * EloquentRepository constructor.
     *
     * @param Application $application
     * @param Config $config
     * @param Cache $cache
     *
     * @throws BindingResolutionException
     */
    public function __construct(Application $application, Config $config, Cache $cache)
    {
        $this->application = $application;
        $this->config = $config;
        $this->cache = $cache;

        if ($this->entity) {
            $this->resolveEntity();
        }
    }

    /**
     * @param string $entity
     *
     * @return self
     * @throws BindingResolutionException
     */
    public function entity(string $entity): self
    {
        $this->entity = $entity;
        $this->resolveEntity();

        return $this;
    }

    /**
     * @param string $relation
     *
     * @return self
     */
    public function relation(string $relation): self
    {
        $this->relation = $relation;

        return $this;
    }

    /**
     * Sets listed criteria for entity.
     *
     * @param mixed ...$criteria
     *
     * @return self
     */
    public function withCriteria(...$criteria): self
    {
        $criteria = Arr::flatten($criteria);

        foreach ($criteria as $criterion) {
            /* @var Criteria\Criterion $criterion */
            $this->modelInstance = $criterion->apply($this->modelInstance);
        }

        return $this;
    }

    /**
     * Defines cache key.
     *
     * @return string
     */
    public function cacheKey(): string
    {
        return $this->modelInstance->getTable();
    }

    /**
     * Cache time-to-live value in seconds.
     *
     * @param int $ttl
     *
     * @return int
     */
    public function cacheTTL(int $ttl = 3600): int
    {
        return $ttl;
    }

    /**
     * Removes cache for model.
     *
     * @param Model $modelInstance
     */
    public function invalidateCache($modelInstance): void
    {
        $this->cache->forget(
            $this->cacheKey() . '.*'
        );
        $this->cache->forget(
            $this->cacheKey() . '.' . $modelInstance->id
        );
    }

    /**
     * Resolves entity.
     *
     * @throws BindingResolutionException
     */
    private function resolveEntity(): void
    {
        $this->modelInstance = $this->application->make($this->entity);

        if (! $this->modelInstance instanceof Model) {
            throw new \InvalidArgumentException(
                $this->entity . ' is not instance of "Illuminate\Database\Eloquent\Model"'
            );
        }
    }

    /**
     * Throws ModelNotFoundException exception.
     *
     * @param array|int $ids
     */
    private function throwModelNotFoundException($ids = [])
    {
        throw (new ModelNotFoundException())->setModel(
            get_class($this->modelInstance->getModel()),
            $ids
        );
    }
}
