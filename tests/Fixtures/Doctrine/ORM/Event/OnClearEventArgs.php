<?php

namespace Doctrine\ORM\Event;

use Doctrine\ORM\EntityManagerInterface;

class OnClearEventArgs
{
    /** @var EntityManagerInterface */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }
}
