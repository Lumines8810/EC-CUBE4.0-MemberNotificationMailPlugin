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
     * 管理者向けメール件名.
     *
     * @var string
     *
     * @ORM\Column(name="admin_subject", type="string", length=255, nullable=false)
     */
    private $admin_subject = '会員情報変更通知（管理者向け）';

    /**
     * 会員向けメール件名.
     *
     * @var string
     *
     * @ORM\Column(name="member_subject", type="string", length=255, nullable=false)
     */
    private $member_subject = '会員情報が変更されました';

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

    /**
     * @return string
     */
    public function getAdminSubject()
    {
        return $this->admin_subject;
    }

    /**
     * @param string $admin_subject
     *
     * @return $this
     */
    public function setAdminSubject($admin_subject)
    {
        $this->admin_subject = $admin_subject;

        return $this;
    }

    /**
     * @return string
     */
    public function getMemberSubject()
    {
        return $this->member_subject;
    }

    /**
     * @param string $member_subject
     *
     * @return $this
     */
    public function setMemberSubject($member_subject)
    {
        $this->member_subject = $member_subject;

        return $this;
    }
}
