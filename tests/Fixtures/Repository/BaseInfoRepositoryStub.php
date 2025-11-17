<?php

namespace Plugin\CustomerChangeNotify\Tests\Fixtures\Repository;

use Eccube\Entity\BaseInfo;

class BaseInfoRepositoryStub
{
    /** @var BaseInfo */
    private $baseInfo;

    public function __construct(BaseInfo $baseInfo)
    {
        $this->baseInfo = $baseInfo;
    }

    public function get(): BaseInfo
    {
        return $this->baseInfo;
    }
}
