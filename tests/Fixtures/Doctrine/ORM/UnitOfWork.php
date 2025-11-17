<?php

namespace Doctrine\ORM;

class UnitOfWork
{
    /** @var EntityManagerInterface */
    private $em;

    /** @var array<string, object> */
    private $entities = [];

    /** @var array<string, array<string, mixed>> */
    private $originalData = [];

    /** @var array<object> */
    private $scheduledEntityUpdates = [];

    /** @var array<string, array<string, array{0: mixed, 1: mixed}>> */
    private $changeSets = [];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function persist($entity): void
    {
        $oid = spl_object_hash($entity);
        $this->entities[$oid] = $entity;
        $this->originalData[$oid] = $this->extractData($entity);
    }

    public function computeChangeSets(): void
    {
        $this->scheduledEntityUpdates = [];
        $this->changeSets = [];

        foreach ($this->entities as $oid => $entity) {
            $current = $this->extractData($entity);
            $original = $this->originalData[$oid] ?? [];
            $changes = [];

            foreach ($current as $field => $value) {
                $old = $original[$field] ?? null;
                if ($old !== $value) {
                    $changes[$field] = [$old, $value];
                }
            }

            if (!empty($changes)) {
                $this->scheduledEntityUpdates[] = $entity;
                $this->changeSets[$oid] = $changes;
            }
        }
    }

    public function takeSnapshot(): void
    {
        foreach ($this->entities as $oid => $entity) {
            $this->originalData[$oid] = $this->extractData($entity);
        }
    }

    /**
     * @return array<object>
     */
    public function getScheduledEntityUpdates(): array
    {
        return $this->scheduledEntityUpdates;
    }

    public function getEntityChangeSet($entity): array
    {
        $oid = spl_object_hash($entity);

        return $this->changeSets[$oid] ?? [];
    }

    public function clear(): void
    {
        $this->entities = [];
        $this->originalData = [];
        $this->scheduledEntityUpdates = [];
        $this->changeSets = [];
    }

    private function extractData($entity): array
    {
        $data = [];
        $reflection = new \ReflectionObject($entity);
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $data[$property->getName()] = $property->getValue($entity);
        }

        return $data;
    }
}
