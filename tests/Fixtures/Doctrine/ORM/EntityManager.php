<?php

namespace Doctrine\ORM;

class EntityManager implements EntityManagerInterface
{
    /** @var UnitOfWork */
    private $unitOfWork;

    public function __construct(?UnitOfWork $unitOfWork = null)
    {
        $this->unitOfWork = $unitOfWork ?: new UnitOfWork($this);
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function persist($entity): void
    {
        $this->unitOfWork->persist($entity);
    }

    public function flush(): void
    {
        $this->unitOfWork->computeChangeSets();
    }

    public function clear(): void
    {
        $this->unitOfWork->clear();
    }
}
