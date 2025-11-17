<?php

namespace Eccube\Entity;

class Customer
{
    private $id;
    private $name01;
    private $name02;
    private $email;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName01(string $value): void
    {
        $this->name01 = $value;
    }

    public function getName01(): ?string
    {
        return $this->name01;
    }

    public function setName02(string $value): void
    {
        $this->name02 = $value;
    }

    public function getName02(): ?string
    {
        return $this->name02;
    }

    public function setEmail(string $value): void
    {
        $this->email = $value;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
}
