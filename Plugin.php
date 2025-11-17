<?php
namespace Plugin\CustomerChangeNotify;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\MailTemplate;
use Eccube\Plugin\AbstractPlugin;
use Eccube\Plugin\PluginManagerInterface;

/**
 * 会員情報変更通知プラグイン
 */
class Plugin extends AbstractPlugin
{
    /** @var string 管理者向けメールテンプレの file_name */
    const ADMIN_TEMPLATE_FILE = 'CustomerChangeNotify/admin';

    /** @var string 会員向けメールテンプレの file_name */
    const MEMBER_TEMPLATE_FILE = 'CustomerChangeNotify/member';

    /**
     * プラグインインストール時
     *
     * @param PluginManagerInterface $pluginManager
     */
    public function install(PluginManagerInterface $pluginManager)
    {
        $app = $pluginManager->getApplication();
        /** @var EntityManagerInterface $em */
        $em = $app['orm.em'];

        // 既に作られていないかチェックしつつ MailTemplate を登録
        $this->createMailTemplate($em, self::ADMIN_TEMPLATE_FILE, '会員情報変更通知（管理者向け）');
        $this->createMailTemplate($em, self::MEMBER_TEMPLATE_FILE, '会員情報変更通知（会員向け）');

        $em->flush();
    }

    /**
     * プラグインアンインストール時
     *
     * @param PluginManagerInterface $pluginManager
     */
    public function uninstall(PluginManagerInterface $pluginManager)
    {
        $app = $pluginManager->getApplication();
        /** @var EntityManagerInterface $em */
        $em = $app['orm.em'];
        $repo = $em->getRepository(MailTemplate::class);

        foreach ([self::ADMIN_TEMPLATE_FILE, self::MEMBER_TEMPLATE_FILE] as $fileName) {
            /** @var MailTemplate|null $mt */
            $mt = $repo->findOneBy(['file_name' => $fileName]);
            if ($mt) {
                $em->remove($mt);
            }
        }

        $em->flush();
    }

    /**
     * プラグイン有効化時
     *
     * @param PluginManagerInterface $pluginManager
     */
    public function enable(PluginManagerInterface $pluginManager)
    {
        // 必要に応じて実装
    }

    /**
     * プラグイン無効化時
     *
     * @param PluginManagerInterface $pluginManager
     */
    public function disable(PluginManagerInterface $pluginManager)
    {
        // 必要に応じて実装
    }

    /**
     * プラグインアップデート時
     *
     * @param PluginManagerInterface $pluginManager
     */
    public function update(PluginManagerInterface $pluginManager)
    {
        // バージョンアップ時の追加処理があれば実装
    }

    /**
     * MailTemplate を 1 件作成（重複防止付き）
     *
     * @param EntityManagerInterface $em
     * @param string                 $fileName   テンプレファイル名（Twig のパスと対応）
     * @param string                 $name       管理画面に表示されるテンプレ名
     */
    protected function createMailTemplate(EntityManagerInterface $em, string $fileName, string $name): void
    {
        $repo = $em->getRepository(MailTemplate::class);

        // 既存チェック
        /** @var MailTemplate|null $existing */
        $existing = $repo->findOneBy(['file_name' => $fileName]);
        if ($existing) {
            return;
        }

        $mt = new MailTemplate();
        $mt->setName($name);
        $mt->setFileName($fileName);
        // 初期状態では有効化（必要なら false でも可）
        $mt->setDelFlg(0);

        $em->persist($mt);
    }
}
