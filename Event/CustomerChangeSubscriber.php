<?php

namespace Plugin\CustomerChangeNotify\Event;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Eccube\Entity\Customer;
use Plugin\CustomerChangeNotify\Service\NotificationService;
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
     * @var array<int, array{customer: Customer, diff: \Plugin\CustomerChangeNotify\Service\Diff, request: ?\Symfony\Component\HttpFoundation\Request}>
     */
    private $pendingNotifications = [];

    /**
     * @param NotificationService $notificationService
     * @param RequestStack        $requestStack
     */
    public function __construct(NotificationService $notificationService, RequestStack $requestStack)
    {
        $this->notificationService = $notificationService;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
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

        $request = $this->requestStack->getCurrentRequest();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Customer) {
                continue;
            }

            // Doctrine が持つ変更セットを取得.
            $changeSet = $uow->getEntityChangeSet($entity);

            // 差分がなければ何もしない.
            $diff = $this->notificationService->buildDiff($entity, $changeSet);
            if ($diff->isEmpty()) {
                continue;
            }

            // ここでは差分だけをキューに積み、トランザクション完了後に送信する.
            $this->pendingNotifications[] = [
                'customer' => $entity,
                'diff'     => $diff,
                'request'  => $request,
            ];
        }
    }

    /**
     * postFlush イベントでキューされた通知をまとめて送信する.
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        try {
            foreach ($this->pendingNotifications as $notification) {
                $this->notificationService->notify(
                    $notification['customer'],
                    $notification['diff'],
                    $notification['request']
                );
            }
        } finally {
            // flush のたびにリセットする.
            $this->pendingNotifications = [];
        }
    }
}
