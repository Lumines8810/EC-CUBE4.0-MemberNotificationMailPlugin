<?php

namespace Plugin\CustomerChangeNotify\Tests\Event;

use PHPUnit\Framework\TestCase;
use Plugin\CustomerChangeNotify\Event\CustomerChangeSubscriber;
use Plugin\CustomerChangeNotify\Service\NotificationService;
use Plugin\CustomerChangeNotify\Service\Diff;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\Events;

require_once __DIR__ . '/../../Event/CustomerChangeSubscriber.php';
require_once __DIR__ . '/../../Service/DiffBuilder.php';

/**
 * CustomerChangeSubscriber のユニットテスト.
 *
 * 注: このテストは基本的な構造を提供します。
 * Doctrine の完全なモックが必要な統合テストは別途作成してください。
 */
class CustomerChangeSubscriberTest extends TestCase
{
    /**
     * @var NotificationService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $notificationService;

    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
     */
    private $requestStack;

    /**
     * @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $logger;

    /**
     * @var CustomerChangeSubscriber
     */
    private $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationService = $this->createMock(NotificationService::class);
        $this->requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new CustomerChangeSubscriber(
            $this->notificationService,
            $this->requestStack,
            $this->logger
        );
    }

    /**
     * getSubscribedEvents が適切なイベントを返すことを確認.
     */
    public function testGetSubscribedEvents(): void
    {
        $events = $this->subscriber->getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertContains(Events::onFlush, $events);
        $this->assertContains(Events::postFlush, $events);
    }

    /**
     * postFlush で空の通知キューの場合、何もしないことを確認.
     */
    public function testPostFlushWithEmptyQueueDoesNothing(): void
    {
        $postFlushArgs = $this->createMock(\Doctrine\ORM\Event\PostFlushEventArgs::class);

        // NotificationService::notify が呼ばれないことを確認
        $this->notificationService
            ->expects($this->never())
            ->method('notify');

        $this->subscriber->postFlush($postFlushArgs);
    }

    /**
     * onFlush で Customer 以外のエンティティは無視されることを確認.
     *
     * 注: 完全なテストには Doctrine の UnitOfWork のモックが必要です。
     * このテストは基本的な構造を示すものです。
     */
    public function testOnFlushIgnoresNonCustomerEntities(): void
    {
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);

        $em->method('getUnitOfWork')->willReturn($uow);

        // Customer 以外のエンティティを返す
        $otherEntity = new \stdClass();
        $uow->method('getScheduledEntityUpdates')->willReturn([$otherEntity]);

        $onFlushArgs = $this->createMock(\Doctrine\ORM\Event\OnFlushEventArgs::class);
        $onFlushArgs->method('getEntityManager')->willReturn($em);

        // NotificationService::buildDiff が呼ばれないことを確認
        $this->notificationService
            ->expects($this->never())
            ->method('buildDiff');

        $this->subscriber->onFlush($onFlushArgs);
    }

    /**
     * onFlush で差分が空の場合、通知がキューに追加されないことを確認.
     *
     * 注: 完全なテストには Doctrine エンティティのモックが必要です。
     */
    public function testOnFlushWithEmptyDiffDoesNotQueue(): void
    {
        $customer = $this->createMock(\Eccube\Entity\Customer::class);
        $customer->method('getId')->willReturn(789);
        $customer->method('getEmail')->willReturn('test@example.com');

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $uow = $this->createMock(\Doctrine\ORM\UnitOfWork::class);

        $em->method('getUnitOfWork')->willReturn($uow);
        $uow->method('getScheduledEntityUpdates')->willReturn([$customer]);
        $uow->method('getEntityChangeSet')->willReturn([]);

        $emptyDiff = new Diff();
        $this->notificationService
            ->method('buildDiff')
            ->willReturn($emptyDiff);

        $onFlushArgs = $this->createMock(\Doctrine\ORM\Event\OnFlushEventArgs::class);
        $onFlushArgs->method('getEntityManager')->willReturn($em);

        // ログが記録されることを確認（変更検知のログ）
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('debug');

        $this->subscriber->onFlush($onFlushArgs);

        // postFlush で何も通知されないことを後で確認できる
        $postFlushArgs = $this->createMock(\Doctrine\ORM\Event\PostFlushEventArgs::class);
        $this->notificationService
            ->expects($this->never())
            ->method('notify');

        $this->subscriber->postFlush($postFlushArgs);
    }

    public function testMultipleFlushesNotifyAllDetectedChanges(): void
    {
        $customer1 = $this->createMock(\Eccube\Entity\Customer::class);
        $customer1->method('getId')->willReturn(1);
        $customer1->method('getEmail')->willReturn('first@example.com');

        $customer2 = $this->createMock(\Eccube\Entity\Customer::class);
        $customer2->method('getId')->willReturn(2);
        $customer2->method('getEmail')->willReturn('second@example.com');

        $diff1 = new Diff();
        $diff1->addChange('email', 'メールアドレス', 'old1@example.com', 'new1@example.com', 'old1@example.com', 'new1@example.com');

        $diff2 = new Diff();
        $diff2->addChange('email', 'メールアドレス', 'old2@example.com', 'new2@example.com', 'old2@example.com', 'new2@example.com');

        $this->notificationService
            ->method('buildDiff')
            ->willReturnOnConsecutiveCalls($diff1, $diff2);

        $this->notificationService
            ->expects($this->exactly(2))
            ->method('notify')
            ->withConsecutive(
                [$customer1, $diff1, $this->anything()],
                [$customer2, $diff2, $this->anything()]
            );

        $this->requestStack
            ->method('getCurrentRequest')
            ->willReturn(null);

        // 1 回目の flush
        $em1 = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $uow1 = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $em1->method('getUnitOfWork')->willReturn($uow1);
        $uow1->method('getScheduledEntityUpdates')->willReturn([$customer1]);
        $uow1->method('getEntityChangeSet')->willReturn(['email' => ['old1@example.com', 'new1@example.com']]);

        $onFlushArgs1 = $this->createMock(\Doctrine\ORM\Event\OnFlushEventArgs::class);
        $onFlushArgs1->method('getEntityManager')->willReturn($em1);

        $this->subscriber->onFlush($onFlushArgs1);
        $this->subscriber->postFlush($this->createMock(\Doctrine\ORM\Event\PostFlushEventArgs::class));

        // 2 回目の flush（同一リクエスト内）
        $em2 = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $uow2 = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $em2->method('getUnitOfWork')->willReturn($uow2);
        $uow2->method('getScheduledEntityUpdates')->willReturn([$customer2]);
        $uow2->method('getEntityChangeSet')->willReturn(['email' => ['old2@example.com', 'new2@example.com']]);

        $onFlushArgs2 = $this->createMock(\Doctrine\ORM\Event\OnFlushEventArgs::class);
        $onFlushArgs2->method('getEntityManager')->willReturn($em2);

        $this->subscriber->onFlush($onFlushArgs2);
        $this->subscriber->postFlush($this->createMock(\Doctrine\ORM\Event\PostFlushEventArgs::class));
    }
}
