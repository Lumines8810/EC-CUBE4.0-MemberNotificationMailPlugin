<?php

namespace Plugin\CustomerChangeNotify;

use Eccube\Common\EccubeNav;

/**
 * 管理画面ナビゲーション設定.
 */
class Nav implements EccubeNav
{
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public static function getNav()
    {
        return [
            'setting' => [
                'children' => [
                    'customer_change_notify' => [
                        'name' => '会員情報変更通知設定',
                        'url' => 'customer_change_notify_admin_config',
                    ],
                ],
            ],
        ];
    }
}
