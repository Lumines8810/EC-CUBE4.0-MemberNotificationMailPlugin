<?php

namespace Doctrine\ORM;

interface EntityManagerInterface
{
    public function getUnitOfWork(): UnitOfWork;
}
