<?php

namespace Plugin\CustomerChangeNotify\Tests;

use PHPUnit\Framework\TestCase;
use Plugin\CustomerChangeNotify\PluginManager;

require_once __DIR__ . '/../PluginManager.php';

/**
 * PluginManager のマイグレーション処理に関する回帰テスト.
 */
class PluginManagerTest extends TestCase
{
    public function testMigrateMailTemplateFileNamesFlushesOnceAndRemovesDuplicate(): void
    {
        $legacy = new \Eccube\Entity\MailTemplate();
        $legacy->setFileName('CustomerChangeNotify/admin');

        $current = new \Eccube\Entity\MailTemplate();
        $current->setFileName(PluginManager::ADMIN_TEMPLATE_FILE);

        $em = new TestEntityManager([$legacy, $current]);

        $manager = new class() extends PluginManager {
            public function invokeMigrate(\Doctrine\ORM\EntityManagerInterface $em): void
            {
                $this->migrateMailTemplateFileNames($em);
            }
        };

        $manager->invokeMigrate($em);

        $this->assertSame(1, $em->getFlushCount());

        $templates = $em->getTemplates();
        $this->assertCount(1, $templates);
        $this->assertSame(
            PluginManager::ADMIN_TEMPLATE_FILE,
            $templates[0]->getFileName(),
            'Legacy template should be renamed and duplicated template removed.'
        );
    }
}

class TestEntityManager implements \Doctrine\ORM\EntityManagerInterface
{
    /**
     * @var TestMailTemplateRepository
     */
    private $repository;

    /**
     * @var array<int, \Eccube\Entity\MailTemplate>
     */
    private $templates = [];

    /**
     * @var int
     */
    private $flushCount = 0;

    /**
     * @param array<int, \Eccube\Entity\MailTemplate> $templates
     */
    public function __construct(array $templates)
    {
        $this->templates = array_values($templates);
        $this->repository = new TestMailTemplateRepository($this->templates);
    }

    public function getRepository($className)
    {
        return $this->repository;
    }

    public function persist($object)
    {
        if ($object instanceof \Eccube\Entity\MailTemplate && !in_array($object, $this->templates, true)) {
            $this->templates[] = $object;
        }
    }

    public function remove($object)
    {
        if ($object instanceof \Eccube\Entity\MailTemplate) {
            $this->repository->removeFromStorage($object);
        }
    }

    public function flush()
    {
        ++$this->flushCount;
    }

    /**
     * @return array<int, \Eccube\Entity\MailTemplate>
     */
    public function getTemplates(): array
    {
        return array_values($this->templates);
    }

    public function getFlushCount(): int
    {
        return $this->flushCount;
    }
}

class TestMailTemplateRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @var array<int, \Eccube\Entity\MailTemplate>
     */
    private $templates;

    /**
     * @param array<int, \Eccube\Entity\MailTemplate> $templates
     */
    public function __construct(array &$templates)
    {
        $this->templates = &$templates;
    }

    public function findOneBy(array $criteria)
    {
        if (!array_key_exists('file_name', $criteria)) {
            return null;
        }

        foreach ($this->templates as $template) {
            if ($template->getFileName() === $criteria['file_name']) {
                return $template;
            }
        }

        return null;
    }

    public function removeFromStorage(\Eccube\Entity\MailTemplate $template): void
    {
        foreach ($this->templates as $index => $item) {
            if ($item === $template) {
                unset($this->templates[$index]);

                return;
            }
        }
    }
}
