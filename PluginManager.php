<?php
namespace Plugin\CustomerChangeNotify;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Eccube\Entity\MailTemplate;
use Eccube\Plugin\AbstractPluginManager;
use Plugin\CustomerChangeNotify\Entity\Config;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 会員情報変更通知プラグイン
 */
class PluginManager extends AbstractPluginManager
{
    /** @var string 管理者向けメールテンプレの file_name */
    const ADMIN_TEMPLATE_FILE = 'CustomerChangeNotify/Mail/customer_change_admin_mail';

    /** @var string 会員向けメールテンプレの file_name */
    const MEMBER_TEMPLATE_FILE = 'CustomerChangeNotify/Mail/customer_change_member_mail';

    /** @var array<string, string> 旧テンプレートパスのマッピング */
    private const LEGACY_TEMPLATE_FILE_MAP = [
        'CustomerChangeNotify/admin' => self::ADMIN_TEMPLATE_FILE,
        'CustomerChangeNotify/member' => self::MEMBER_TEMPLATE_FILE,
    ];

    /**
     * プラグインインストール時
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function install(array $meta, ContainerInterface $container)
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        // Config テーブルを作成
        $this->createConfigTable($em);

        // 既に作られていないかチェックしつつ MailTemplate を登録
        $this->createMailTemplate($em, self::ADMIN_TEMPLATE_FILE, '会員情報変更通知（管理者向け）');
        $this->createMailTemplate($em, self::MEMBER_TEMPLATE_FILE, '会員情報変更通知（会員向け）');

        $em->flush();
    }

    /**
     * プラグインアンインストール時
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function uninstall(array $meta, ContainerInterface $container)
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $repo = $em->getRepository(MailTemplate::class);

        foreach ([self::ADMIN_TEMPLATE_FILE, self::MEMBER_TEMPLATE_FILE] as $fileName) {
            /** @var MailTemplate|null $mt */
            $mt = $repo->findOneBy(['file_name' => $fileName]);
            if ($mt) {
                $em->remove($mt);
            }
        }

        // Config テーブルを削除
        $this->dropConfigTable($em);

        $em->flush();
    }

    /**
     * プラグイン有効化時
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function enable(array $meta, ContainerInterface $container)
    {
        // 必要に応じて実装
    }

    /**
     * プラグイン無効化時
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function disable(array $meta, ContainerInterface $container)
    {
        // 必要に応じて実装
    }

    /**
     * プラグインアップデート時
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function update(array $meta, ContainerInterface $container)
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        // 以前のバージョンからアップグレードする際にも Config テーブルが確実に存在するようにする.
        $this->createConfigTable($em);

        // 旧テンプレートパスを最新の Twig パスへ移行
        $this->migrateMailTemplateFileNames($em);

        $em->flush();
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

    /**
     * 旧バージョンで作成された MailTemplate の file_name を Twig の実パスへ移行する.
     *
     * このメソッドは以下の動作を行います:
     *   1. 既存のレガシーなテンプレート（旧 file_name）を新しい file_name へ更新します。
     *   2. 新しい file_name で既に別のテンプレートが存在する場合は、その重複テンプレートを削除します。
     *   3. この処理は冪等であり、複数回実行しても安全です。
     *
     * @param EntityManagerInterface $em
     */
    protected function migrateMailTemplateFileNames(EntityManagerInterface $em): void
    {
        $repo = $em->getRepository(MailTemplate::class);

        foreach (self::LEGACY_TEMPLATE_FILE_MAP as $legacy => $current) {
            /** @var MailTemplate|null $legacyTemplate */
            $legacyTemplate = $repo->findOneBy(['file_name' => $legacy]);
            if (!$legacyTemplate) {
                continue;
            }

            /** @var MailTemplate|null $currentTemplate */
            $currentTemplate = $repo->findOneBy(['file_name' => $current]);
            if ($currentTemplate && $currentTemplate !== $legacyTemplate) {
                $em->remove($currentTemplate);
                $em->flush();
                $em->flush();
            }

            $legacyTemplate->setFileName($current);
            $em->persist($legacyTemplate);
        }
    }

    /**
     * Config テーブルを作成.
     *
     * @param EntityManagerInterface $em
     */
    protected function createConfigTable(EntityManagerInterface $em): void
    {
        $metadata = $em->getClassMetadata(Config::class);
        $schemaTool = new SchemaTool($em);

        try {
            // テーブルが存在しない場合のみ作成
            $schemaTool->createSchema([$metadata]);
        } catch (\Exception $e) {
            // テーブルが既に存在する場合は無視
        }
    }

    /**
     * Config テーブルを削除.
     *
     * @param EntityManagerInterface $em
     */
    protected function dropConfigTable(EntityManagerInterface $em): void
    {
        $metadata = $em->getClassMetadata(Config::class);
        $schemaTool = new SchemaTool($em);

        try {
            // テーブルを削除
            $schemaTool->dropSchema([$metadata]);
        } catch (\Exception $e) {
            // テーブルが存在しない場合は無視
        }
    }
}
