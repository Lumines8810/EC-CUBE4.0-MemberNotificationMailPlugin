<?php

namespace Plugin\CustomerChangeNotify\Event;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Eccube\Entity\Customer;
use Plugin\CustomerChangeNotify\Service\NotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Customer エンティティの更新を監視して差分を通知する Doctrine イベントサブスクライバ.
 */
class CustomerChangeSubscriber implements EventSubscriber
{
    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array<int, array{customer: Customer, diff: \Plugin\CustomerChangeNotify\Service\Diff, request: ?\Symfony\Component\HttpFoundation\Request}>
     */
    private $pendingNotifications = [];

    /**
     * @param NotificationService $notificationService
     * @param RequestStack        $requestStack
     * @param LoggerInterface     $logger
     */
    public function __construct(
        NotificationService $notificationService,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->notificationService = $notificationService;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
            Events::onClear,
            Events::onFlush,
            Events::postFlush,
        ];
    }

    /**
     * onFlush イベントで Customer の変更差分を検知し通知処理を呼び出す.
     *
     * @param OnFlushEventArgs $args
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        // If a previous flush aborted before postFlush ran, stale notifications
        // could remain queued. Ensure each flush cycle starts with a clean slate
        // so rolled-back changes are not delivered on a later successful flush.
        $this->resetPendingNotifications('onFlush');

        $request = $this->requestStack->getCurrentRequest();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Customer) {
                continue;
            }

            $this->logger->debug('[CustomerChangeNotify] Customer エンティティの更新を検知', [
                'customer_id' => $entity->getId(),
                'customer_email' => $entity->getEmail(),
            ]);

            // Doctrine が持つ変更セットを取得.
            $changeSet = $uow->getEntityChangeSet($entity);

            // 差分がなければ何もしない.
            $diff = $this->notificationService->buildDiff($entity, $changeSet);
            if ($diff->isEmpty()) {
                $this->logger->debug('[CustomerChangeNotify] 監視対象フィールドの変更なし', [
                    'customer_id' => $entity->getId(),
                ]);
                continue;
            }

            // ここでは差分だけをキューに積み、トランザクション完了後に送信する.
            $this->pendingNotifications[] = [
                'customer' => $entity,
                'diff'     => $diff,
                'request'  => $request,
            ];

            $this->logger->info('[CustomerChangeNotify] 通知をキューに追加', [
                'customer_id' => $entity->getId(),
                'queue_size' => count($this->pendingNotifications),
            ]);
        }
    }

    /**
     * postFlush イベントでキューされた通知をまとめて送信する.
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pendingNotifications)) {
            return;
        }

        $this->logger->info('[CustomerChangeNotify] postFlush: 通知処理を開始', [
            'notification_count' => count($this->pendingNotifications),
        ]);

        try {
            foreach ($this->pendingNotifications as $notification) {
                $this->notificationService->notify(
                    $notification['customer'],
                    $notification['diff'],
                    $notification['request']
                );
            }

            $this->logger->info('[CustomerChangeNotify] postFlush: 全通知処理完了', [
                'processed_count' => count($this->pendingNotifications),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[CustomerChangeNotify] postFlush: 通知処理でエラー発生', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->resetPendingNotifications('postFlush');
        }
    }

    /**
     * clear イベントで通知キューをリセットする.
     */
    public function onClear(OnClearEventArgs $args): void
    {
        $this->resetPendingNotifications('onClear');
    }

    private function resetPendingNotifications(string $reason): void
    {
        if (empty($this->pendingNotifications)) {
            return;
        }

        $queueSize = count($this->pendingNotifications);
        $this->pendingNotifications = [];

        $this->logger->debug(sprintf('[CustomerChangeNotify] %s: 通知キューをリセット', $reason), [
            'cleared_count' => $queueSize,
        ]);
    }
}
