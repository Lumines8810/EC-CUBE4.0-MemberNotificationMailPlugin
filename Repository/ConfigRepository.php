<?php

namespace Plugin\CustomerChangeNotify\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use Eccube\Repository\AbstractRepository;
use Plugin\CustomerChangeNotify\Entity\Config;

// Doctrine ORM 2.10+ moves ManagerRegistry to Doctrine\Persistence. EC-CUBE 4.0.3
// still ships Doctrine\Common\Persistence but a composer update can drop it,
// causing a fatal "Class 'Doctrine\\Common\\Persistence\\ManagerRegistry' not
// found" error when the repository is autoloaded. Provide a runtime alias to the
// new namespace so both dependency stacks work.
if (!interface_exists(ManagerRegistry::class) && interface_exists(\Doctrine\Persistence\ManagerRegistry::class)) {
    class_alias(\Doctrine\Persistence\ManagerRegistry::class, ManagerRegistry::class);
}

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
