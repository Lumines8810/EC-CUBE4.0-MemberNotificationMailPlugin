<?php
namespace Plugin\CustomerChangeNotify;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Eccube\Entity\MailTemplate;
use Eccube\Plugin\AbstractPluginManager;
use Plugin\CustomerChangeNotify\Entity\Config;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * 会員情報変更通知プラグイン
 */
class PluginManager extends AbstractPluginManager
{
    /** @var string 管理者向けメールテンプレの file_name */
    const ADMIN_TEMPLATE_FILE = 'CustomerChangeNotify/Mail/customer_change_admin_mail.twig';

    /** @var string 会員向けメールテンプレの file_name */
    const MEMBER_TEMPLATE_FILE = 'CustomerChangeNotify/Mail/customer_change_member_mail.twig';

    /** @var array<string, string> 旧テンプレートパスのマッピング */
    private const LEGACY_TEMPLATE_FILE_MAP = [
        'CustomerChangeNotify/admin' => self::ADMIN_TEMPLATE_FILE,
        'CustomerChangeNotify/member' => self::MEMBER_TEMPLATE_FILE,
        '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_admin_mail' => self::ADMIN_TEMPLATE_FILE,
        '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_admin_mail.twig' => self::ADMIN_TEMPLATE_FILE,
        '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_member_mail' => self::MEMBER_TEMPLATE_FILE,
        '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_member_mail.twig' => self::MEMBER_TEMPLATE_FILE,
        'CustomerChangeNotify/Mail/customer_change_admin_mail' => self::ADMIN_TEMPLATE_FILE,
        'CustomerChangeNotify/Mail/customer_change_member_mail' => self::MEMBER_TEMPLATE_FILE,
    ];

    /**
     * プラグインインストール時
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function install(array $meta, ContainerInterface $container)
    {
        $em = $this->getEntityManager($container);
        $this->createSchema($em);
        $this->createInitialConfig($em);
        $this->createMailTemplates($em);
        $this->publishMailTemplates($container);
    }

    /**
     * プラグインアンインストール時
     *
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function uninstall(array $meta, ContainerInterface $container)
    {
        $em = $this->getEntityManager($container);
        $this->deleteMailTemplates($em);
        $this->dropSchema($em);
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
        $em = $this->getEntityManager($container);
        $this->createSchema($em);
        $this->migrateMailTemplateFileNames($em);
        $this->createMailTemplates($em);
        $this->publishMailTemplates($container);
    }

    private function getEntityManager(ContainerInterface $container): EntityManagerInterface
    {
        return $container->get('doctrine.orm.entity_manager');
    }

    private function createSchema(EntityManagerInterface $em): void
    {
        $metadata = $em->getClassMetadata(Config::class);
        $tool = new SchemaTool($em);

        try {
            $tool->createSchema([$metadata]);
        } catch (Throwable $e) {
            // 既存スキーマがある場合は握りつぶす
        }
    }

    private function dropSchema(EntityManagerInterface $em): void
    {
        $metadata = $em->getClassMetadata(Config::class);
        $tool = new SchemaTool($em);

        try {
            $tool->dropSchema([$metadata]);
        } catch (Throwable $e) {
            // 既に削除されていれば何もしない
        }
    }

    private function createMailTemplates(EntityManagerInterface $em): void
    {
        $repo = $em->getRepository(MailTemplate::class);
        $definitions = [
            self::ADMIN_TEMPLATE_FILE => '会員情報変更通知（管理者向け）',
            self::MEMBER_TEMPLATE_FILE => '会員情報が変更されました',
        ];

        foreach ($definitions as $file => $name) {
            if ($repo->findOneBy(['file_name' => $file])) {
                continue;
            }

            $template = new MailTemplate();
            $template->setName($name);
            $template->setFileName($file);
            $template->setMailSubject($file === self::ADMIN_TEMPLATE_FILE ? '会員情報変更通知（管理者向け）' : '会員情報が変更されました');
            $em->persist($template);
        }

        $em->flush();
    }

    private function deleteMailTemplates(EntityManagerInterface $em): void
    {
        $repo = $em->getRepository(MailTemplate::class);

        foreach ([self::ADMIN_TEMPLATE_FILE, self::MEMBER_TEMPLATE_FILE] as $file) {
            if ($template = $repo->findOneBy(['file_name' => $file])) {
                $em->remove($template);
            }
        }

        $em->flush();
    }

    private function migrateMailTemplateFileNames(EntityManagerInterface $em): void
    {
        $repo = $em->getRepository(MailTemplate::class);
        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
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
                }

                $legacyTemplate->setFileName($current);
                $em->persist($legacyTemplate);
            }

            $em->flush();
            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * 初期設定レコードを作成する.
     *
     * IMPORTANT: ConfigRepository::get() が postFlush 中に flush() を呼び出すと
     * Segmentation fault が発生するため、インストール時に必ず Config レコードを作成します。
     *
     * @param EntityManagerInterface $em
     */
    private function createInitialConfig(EntityManagerInterface $em): void
    {
        $repo = $em->getRepository(Config::class);
        $existingConfig = $repo->find(1);

        if (!$existingConfig) {
            $config = new Config();
            // デフォルト値は Entity で設定されている
            $em->persist($config);
            $em->flush();
        }
    }

    private function publishMailTemplates(ContainerInterface $container): void
    {
        $filesystem = new Filesystem();
        $sourceDir = __DIR__.'/Resource/template/CustomerChangeNotify/Mail';
        $targetDir = rtrim($container->getParameter('eccube_theme_front_dir'), '/').'/CustomerChangeNotify/Mail';

        $filesystem->mkdir($targetDir);

        foreach (['customer_change_admin_mail.twig', 'customer_change_member_mail.twig'] as $file) {
            $target = $targetDir.'/'.$file;
            if ($filesystem->exists($target)) {
                continue;
            }
            $filesystem->copy($sourceDir.'/'.$file, $target);
        }
    }
}
