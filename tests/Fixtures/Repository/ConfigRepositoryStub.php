<?php

namespace Plugin\CustomerChangeNotify\Tests\Fixtures\Repository;

use Plugin\CustomerChangeNotify\Entity\Config;

class ConfigRepositoryStub
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function get($id = 1): Config
    {
        return $this->config;
    }
}
