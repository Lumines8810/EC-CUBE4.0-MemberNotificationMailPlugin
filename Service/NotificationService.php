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

            $this->sendMail(
                'CustomerChangeNotify/Mail/customer_change_admin_mail.twig',
                $Config->getAdminSubject(),
                $adminTo,
                $context,
                '管理者向けメール',
                $fromAddress,
                $fromName
            );

            $this->sendMail(
                'CustomerChangeNotify/Mail/customer_change_member_mail.twig',
                $Config->getMemberSubject(),
                $customer->getEmail(),
                $context,
                '会員向けメール',
                $fromAddress,
                $fromName
            );
        } catch (\Exception $e) {
            $this->logger->error('[CustomerChangeNotify] 通知処理で予期しないエラーが発生', [
                'customer_id' => $customer->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // エラーが発生しても、元のトランザクションには影響を与えないようにする
        }
    }

    /**
     * Twig テンプレートをレンダリングしてメール送信する共通ヘルパ.
     */
    private function sendMail(
        string $template,
        string $subject,
        string $recipient,
        array $context,
        string $label,
        string $fromAddress,
        string $fromName
    ): void {
        try {
            $body = $this->twig->render($template, $context);

            $message = (new Swift_Message($subject))
                ->setFrom([$fromAddress => $fromName])
                ->setTo([$recipient])
                ->setBody($body);

            $sent = $this->mailer->send($message);

            if ($sent > 0) {
                $this->logger->info(sprintf('[CustomerChangeNotify] %s送信成功', $label), [
                    'to' => $recipient,
                    'subject' => $subject,
                ]);
            } else {
                $this->logger->warning(sprintf('[CustomerChangeNotify] %s送信失敗（送信数: 0）', $label), [
                    'to' => $recipient,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf('[CustomerChangeNotify] %s送信エラー', $label), [
                'to' => $recipient,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
