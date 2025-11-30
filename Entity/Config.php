<?php

namespace Plugin\CustomerChangeNotify\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\AbstractEntity;

/**
 * 会員情報変更通知プラグインの設定エンティティ.
 *
 * @ORM\Table(name="plg_customer_change_notify_config")
 * @ORM\Entity(repositoryClass="Plugin\CustomerChangeNotify\Repository\ConfigRepository")
 */
class Config extends AbstractEntity
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * 管理者通知先メールアドレス.
     *
     * @var string|null
     *
     * @ORM\Column(name="admin_to", type="string", length=255, nullable=true)
     */
    private $admin_to;

    /**
     * 管理者向けメール件名（互換用。実際の件名は MailTemplate で管理）.
     *
     * @var string|null
     *
     * @ORM\Column(name="admin_subject", type="string", length=255, nullable=true)
     */
    private $admin_subject;

    /**
     * 会員向けメール件名（互換用。実際の件名は MailTemplate で管理）.
     *
     * @var string|null
     *
     * @ORM\Column(name="member_subject", type="string", length=255, nullable=true)
     */
    private $member_subject;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getAdminTo()
    {
        return $this->admin_to;
    }

    /**
     * @param string|null $admin_to
     *
     * @return $this
     */
    public function setAdminTo($admin_to)
    {
        $this->admin_to = $admin_to;

        return $this;
    }

    public function getAdminSubject(): ?string
    {
        return $this->admin_subject;
    }

    public function setAdminSubject(?string $admin_subject)
    {
        $this->admin_subject = $admin_subject;

        return $this;
    }

    public function getMemberSubject(): ?string
    {
        return $this->member_subject;
    }

    public function setMemberSubject(?string $member_subject)
    {
        $this->member_subject = $member_subject;

        return $this;
    }
}
