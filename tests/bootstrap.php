<?php

/**
 * PHPUnit bootstrap file for CustomerChangeNotify plugin
 */

// テストのための基本的なセットアップ
// EC-CUBE の環境が必要な場合は、ここで設定を行う

// オートローダーの設定（必要に応じて）
// require_once __DIR__ . '/../../../autoload.php';

// Doctrine / EC-CUBE 依存が無い環境でもテストを実行できるよう、
// 最低限のスタブを定義する.
namespace Doctrine\ORM {
    if (!interface_exists(EntityManagerInterface::class)) {
        interface EntityManagerInterface
        {
            public function getRepository($className);

            public function persist($object);

            public function remove($object);

            public function flush();
        }
    }

    if (!class_exists(EntityRepository::class)) {
        class EntityRepository
        {
        }
    }

    if (!class_exists(Events::class)) {
        class Events
        {
            public const onFlush = 'onFlush';
            public const postFlush = 'postFlush';
        }
    }
}

namespace Doctrine\ORM\Tools {
    if (!class_exists(SchemaTool::class)) {
        class SchemaTool
        {
            public function __construct($em)
            {
            }

            public function createSchema(array $classes): void
            {
            }

            public function dropSchema(array $classes): void
            {
            }
        }
    }
}

namespace Eccube\Entity {
    if (!class_exists(MailTemplate::class)) {
        class MailTemplate
        {
            private $fileName;
            private $name;
            private $delFlg;

            public function setName($name): void
            {
                $this->name = $name;
            }

            public function setFileName($fileName): void
            {
                $this->fileName = $fileName;
            }

            public function getFileName()
            {
                return $this->fileName;
            }

            public function setDelFlg($delFlg): void
            {
                $this->delFlg = $delFlg;
            }
        }
    }

    if (!class_exists(Customer::class)) {
        class Customer
        {
            public function getId()
            {
                return null;
            }

            public function getEmail()
            {
                return null;
            }
        }
    }
}

namespace {
}
