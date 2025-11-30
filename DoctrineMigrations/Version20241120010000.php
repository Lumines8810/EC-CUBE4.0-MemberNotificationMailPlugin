<?php

namespace Plugin\CustomerChangeNotify\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Migrations\AbstractMigration;

class Version20241120010000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE dtb_mail_template
             SET mail_subject = '会員情報が変更されました'
             WHERE file_name = 'CustomerChangeNotify/Mail/customer_change_member_mail.twig'
               AND (mail_subject IS NULL
                    OR mail_subject = ''
                    OR mail_subject = '会員情報変更通知（管理者向け）')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE dtb_mail_template
             SET mail_subject = '会員情報変更通知（管理者向け）'
             WHERE file_name = 'CustomerChangeNotify/Mail/customer_change_member_mail.twig'
               AND (mail_subject IS NULL OR mail_subject = '' OR mail_subject = '会員情報が変更されました')"
        );
    }
}
