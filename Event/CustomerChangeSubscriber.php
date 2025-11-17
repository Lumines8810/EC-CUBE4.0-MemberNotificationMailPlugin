<?php

namespace Plugin\CustomerChangeNotify\Event;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
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

            // 通知実行（管理者・会員 両方のメール送信をこの中で行う）
            $this->notificationService->notify($entity, $diff, $request);
        }
    }
}
