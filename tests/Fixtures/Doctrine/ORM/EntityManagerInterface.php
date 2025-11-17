<?php

namespace Doctrine\ORM;

use Doctrine\ORM\UnitOfWork;
interface EntityManagerInterface
{
    public function getUnitOfWork(): UnitOfWork;
}
