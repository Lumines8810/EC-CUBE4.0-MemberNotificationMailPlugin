<?php

namespace Eccube\Entity;

class BaseInfo
{
    private $email01;
    private $shop_name;

    public function setEmail01(string $email): void
    {
        $this->email01 = $email;
    }

    public function getEmail01(): ?string
    {
        return $this->email01;
    }

    public function setShopName(string $name): void
    {
        $this->shop_name = $name;
    }

    public function getShopName(): ?string
    {
        return $this->shop_name;
    }
}
