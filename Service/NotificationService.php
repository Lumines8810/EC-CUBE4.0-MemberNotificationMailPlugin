<?php

namespace Plugin\CustomerChangeNotify\Service;

use Eccube\Entity\Customer;
use Eccube\Repository\BaseInfoRepository;
use Plugin\CustomerChangeNotify\Service\DiffBuilder;
use Plugin\CustomerChangeNotify\Service\Diff;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\HttpFoundation\Request;

/**
 * 会員情報変更の差分を元に、管理者・会員へメール通知を行うサービス.
 */
class NotificationService
{
    /**
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * @var BaseInfoRepository
     */
    private $baseInfoRepository;

    /**
     * @var DiffBuilder
     */
    private $diffBuilder;

    /**
     * @var string|null
     */
    private $adminTo;

    /**
     * @param Swift_Mailer        $mailer
     * @param \Twig_Environment   $twig
     * @param BaseInfoRepository  $baseInfoRepository
     * @param DiffBuilder         $diffBuilder
     * @param string|null         $adminTo          管理者通知先メールアドレス（null の場合は BaseInfo.email01）
     */
    public function __construct(
        Swift_Mailer $mailer,
        \Twig_Environment $twig,
        BaseInfoRepository $baseInfoRepository,
        DiffBuilder $diffBuilder,
        ?string $adminTo = null
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->baseInfoRepository = $baseInfoRepository;
        $this->diffBuilder = $diffBuilder;
        $this->adminTo = $adminTo;
    }

    /**
     * Doctrine の変更セットから差分オブジェクトを構築する.
     *
     * @param Customer $customer
     * @param array    $changeSet
     *
     * @return Diff
     */
    public function buildDiff(Customer $customer, array $changeSet): Diff
    {
        return $this->diffBuilder->build($customer, $changeSet);
    }

    /**
     * 管理者・会員へ会員情報変更通知メールを送信する.
     *
     * @param Customer     $customer
     * @param Diff         $diff
     * @param Request|null $request
     */
    public function notify(Customer $customer, Diff $diff, ?Request $request = null): void
    {
        // 差分が空なら何もしない.
        if ($diff->isEmpty()) {
            return;
        }

        $BaseInfo = $this->baseInfoRepository->get();

        // From / 管理者宛て
        $fromAddress = $BaseInfo->getEmail01();
        $fromName = $BaseInfo->getShopName();
        $adminTo = $this->adminTo ?: $fromAddress;

        $context = [
            'Customer' => $customer,
            'diff'     => $diff->getChanges(),
            'request'  => $request,
        ];

        // ------------------
        // 管理者向けメール
        // ------------------
        $adminSubject = '会員情報変更通知（管理者向け）';

        $adminBody = $this->twig->render(
            // プラグイン内 Twig (前提として Resource/template/Mail/customer_change_admin_mail.twig が存在)
            'CustomerChangeNotify/Mail/customer_change_admin_mail.twig',
            $context
        );

        $adminMessage = (new Swift_Message($adminSubject))
            ->setFrom([$fromAddress => $fromName])
            ->setTo([$adminTo])
            ->setBody($adminBody);

        $this->mailer->send($adminMessage);

        // ------------------
        // 会員本人向けメール
        // ------------------
        $memberSubject = '会員情報が変更されました';

        $memberBody = $this->twig->render(
            'CustomerChangeNotify/Mail/customer_change_member_mail.twig',
            $context
        );

        $memberMessage = (new Swift_Message($memberSubject))
            ->setFrom([$fromAddress => $fromName])
            ->setTo([$customer->getEmail()])
            ->setBody($memberBody);

        $this->mailer->send($memberMessage);
    }
}
