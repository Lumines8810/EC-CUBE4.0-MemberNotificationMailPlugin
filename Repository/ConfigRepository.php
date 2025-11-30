<?php

namespace Plugin\CustomerChangeNotify\Repository;

use Eccube\Repository\AbstractRepository;
use Plugin\CustomerChangeNotify\Entity\Config;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * ConfigRepository.
 *
 * EC-CUBE 4.0.3 exposes Doctrine's registry via Symfony\Bridge\Doctrine\RegistryInterface.
 */
class ConfigRepository extends AbstractRepository
{
    /**
     * ConfigRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Config::class);
    }

    /**
     * 設定を取得する（存在しない場合はデフォルト値を持つメモリ内オブジェクトを返す）.
     *
     * IMPORTANT: postFlush コールバック中に flush() を呼び出すと Segmentation fault が発生するため、
     * このメソッドは DB に存在しない場合でも flush() を実行しません。
     * Config レコードは PluginManager::install() で作成されます。
     *
     * @param int $id デフォルトは 1
     *
     * @return Config
     */
    public function get($id = 1)
    {
        $Config = $this->find($id);

        if (!$Config) {
            // DB に存在しない場合は、デフォルト値を持つメモリ内オブジェクトを返す
            // postFlush 中の flush() 呼び出しを回避するため、永続化しない
            $Config = new Config();
            // デフォルト値は Entity で設定されている
        }

        return $Config;
    }
}
