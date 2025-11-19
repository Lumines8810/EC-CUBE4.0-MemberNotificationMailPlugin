<?php

namespace Plugin\CustomerChangeNotify\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Eccube\Repository\AbstractRepository;
use Plugin\CustomerChangeNotify\Entity\Config;

/**
 * ConfigRepository.
 */
class ConfigRepository extends AbstractRepository
{
    /**
     * ConfigRepository constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Config::class);
    }

    /**
     * 設定を取得する（存在しない場合は新規作成）.
     *
     * @param int $id デフォルトは 1
     *
     * @return Config
     */
    public function get($id = 1)
    {
        $Config = $this->find($id);

        if (!$Config) {
            $Config = new Config();
            // デフォルト値は Entity で設定されている
        }

        return $Config;
    }
}
