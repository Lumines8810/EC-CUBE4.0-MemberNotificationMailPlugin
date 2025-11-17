<?php

namespace Plugin\CustomerChangeNotify\Service;

use Eccube\Entity\Customer;
use Eccube\Repository\BaseInfoRepository;
use Plugin\CustomerChangeNotify\Repository\ConfigRepository;
use Plugin\CustomerChangeNotify\Service\DiffBuilder;
use Plugin\CustomerChangeNotify\Service\Diff;
use Psr\Log\LoggerInterface;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

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
     * @var Environment
     */
    private $twig;

    /**
     * @var BaseInfoRepository
     */
    private $baseInfoRepository;

    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * @var DiffBuilder
     */
    private $diffBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Swift_Mailer        $mailer
     * @param Environment          $twig
     * @param BaseInfoRepository  $baseInfoRepository
     * @param ConfigRepository    $configRepository
     * @param DiffBuilder         $diffBuilder
     * @param LoggerInterface     $logger
     */
    public function __construct(
        Swift_Mailer $mailer,
        Environment $twig,
        BaseInfoRepository $baseInfoRepository,
        ConfigRepository $configRepository,
        DiffBuilder $diffBuilder,
        LoggerInterface $logger
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->baseInfoRepository = $baseInfoRepository;
        $this->configRepository = $configRepository;
        $this->diffBuilder = $diffBuilder;
        $this->logger = $logger;
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
        $diff = $this->diffBuilder->build($customer, $changeSet);

        if (!$diff->isEmpty()) {
            $this->logger->info('[CustomerChangeNotify] 会員情報の変更を検知', [
                'customer_id' => $customer->getId(),
                'customer_email' => $customer->getEmail(),
                'changed_fields' => array_keys($diff->getChanges()),
            ]);
        }

        return $diff;
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

        try {
            $BaseInfo = $this->baseInfoRepository->get();
            $Config = $this->configRepository->get();

            // From / 管理者宛て
            $fromAddress = $BaseInfo->getEmail01();
            $fromName = $BaseInfo->getShopName();
            $adminTo = $Config->getAdminTo() ?: $fromAddress;

            $context = [
                'Customer' => $customer,
                'diff'     => $diff->getChanges(),
                'request'  => $request,
            ];

            $this->logger->info('[CustomerChangeNotify] メール送信を開始', [
                'customer_id' => $customer->getId(),
                'customer_email' => $customer->getEmail(),
                'admin_to' => $adminTo,
            ]);

            // ------------------
            // 管理者向けメール
            // ------------------
            $adminSubject = $Config->getAdminSubject();

            try {
                $adminBody = $this->twig->render(
                    // プラグイン内 Twig (前提として Resource/template/Mail/customer_change_admin_mail.twig が存在)
                    'CustomerChangeNotify/Mail/customer_change_admin_mail.twig',
                    $context
                );

                $adminMessage = (new Swift_Message($adminSubject))
                    ->setFrom([$fromAddress => $fromName])
                    ->setTo([$adminTo])
                    ->setBody($adminBody);

                $adminSent = $this->mailer->send($adminMessage);

                if ($adminSent > 0) {
                    $this->logger->info('[CustomerChangeNotify] 管理者向けメール送信成功', [
                        'to' => $adminTo,
                        'subject' => $adminSubject,
                    ]);
                } else {
                    $this->logger->warning('[CustomerChangeNotify] 管理者向けメール送信失敗（送信数: 0）', [
                        'to' => $adminTo,
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('[CustomerChangeNotify] 管理者向けメール送信エラー', [
                    'to' => $adminTo,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // ------------------
            // 会員本人向けメール
            // ------------------
            $memberSubject = $Config->getMemberSubject();

            try {
                $memberBody = $this->twig->render(
                    'CustomerChangeNotify/Mail/customer_change_member_mail.twig',
                    $context
                );

                $memberMessage = (new Swift_Message($memberSubject))
                    ->setFrom([$fromAddress => $fromName])
                    ->setTo([$customer->getEmail()])
                    ->setBody($memberBody);

                $memberSent = $this->mailer->send($memberMessage);

                if ($memberSent > 0) {
                    $this->logger->info('[CustomerChangeNotify] 会員向けメール送信成功', [
                        'to' => $customer->getEmail(),
                        'subject' => $memberSubject,
                    ]);
                } else {
                    $this->logger->warning('[CustomerChangeNotify] 会員向けメール送信失敗（送信数: 0）', [
                        'to' => $customer->getEmail(),
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('[CustomerChangeNotify] 会員向けメール送信エラー', [
                    'to' => $customer->getEmail(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('[CustomerChangeNotify] 通知処理で予期しないエラーが発生', [
                'customer_id' => $customer->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // エラーが発生しても、元のトランザクションには影響を与えないようにする
        }
    }
}
