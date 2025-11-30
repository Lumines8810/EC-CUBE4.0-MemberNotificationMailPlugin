<?php

namespace Plugin\CustomerChangeNotify\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Migrations\AbstractMigration;

class Version20241120000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_customer_change_notify_config ALTER admin_subject DROP NOT NULL');
        $this->addSql('ALTER TABLE plg_customer_change_notify_config ALTER member_subject DROP NOT NULL');

        $legacyAdmin = [
            '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_admin_mail.twig',
            '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_admin_mail',
            'CustomerChangeNotify/Mail/customer_change_admin_mail.twig',
        ];
        $legacyMember = [
            '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_member_mail.twig',
            '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_member_mail',
            'CustomerChangeNotify/Mail/customer_change_member_mail.twig',
        ];

        foreach ($legacyAdmin as $legacy) {
            $this->addSql(
                'UPDATE dtb_mail_template SET file_name = :new_file, mail_subject = COALESCE(NULLIF(mail_subject, \'\'), :subject) WHERE file_name = :old_file',
                [
                    'new_file' => 'CustomerChangeNotify/Mail/customer_change_admin_mail.twig',
                    'subject' => '会員情報変更通知（管理者向け）',
                    'old_file' => $legacy,
                ]
            );
        }

        foreach ($legacyMember as $legacy) {
            $this->addSql(
                'UPDATE dtb_mail_template SET file_name = :new_file, mail_subject = COALESCE(NULLIF(mail_subject, \'\'), :subject) WHERE file_name = :old_file',
                [
                    'new_file' => 'CustomerChangeNotify/Mail/customer_change_member_mail.twig',
                    'subject' => '会員情報が変更されました',
                    'old_file' => $legacy,
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_customer_change_notify_config ALTER admin_subject SET NOT NULL');
        $this->addSql('ALTER TABLE plg_customer_change_notify_config ALTER member_subject SET NOT NULL');
    }
}
