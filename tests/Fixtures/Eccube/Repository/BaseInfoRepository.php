<?php

namespace Eccube\Repository;

use Eccube\Entity\BaseInfo;

class BaseInfoRepository extends AbstractRepository
{
    /** @var BaseInfo */
    private $baseInfo;

    public function __construct(BaseInfo $baseInfo = null)
    {
        parent::__construct();
        $this->baseInfo = $baseInfo ?? new BaseInfo();
    }

    public function setBaseInfo(BaseInfo $baseInfo): void
    {
        $this->baseInfo = $baseInfo;
    }

    public function get(): BaseInfo
    {
        return $this->baseInfo;
    }
}
