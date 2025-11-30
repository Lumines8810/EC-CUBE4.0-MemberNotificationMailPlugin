<?php

namespace Plugin\CustomerChangeNotify\Tests\Event;

use PHPUnit\Framework\TestCase;
use Plugin\CustomerChangeNotify\Entity\Config;
use Plugin\CustomerChangeNotify\Event\CustomerChangeSubscriber;
use Plugin\CustomerChangeNotify\Service\Diff;
use Plugin\CustomerChangeNotify\Service\DiffBuilder;
use Plugin\CustomerChangeNotify\Service\NotificationService;
use Plugin\CustomerChangeNotify\Tests\Fixtures\Logger\ArrayLogger;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Customer;
use Swift_Mailer;
use Swift_Transport_CapturingTransport;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class CustomerChangeSubscriberTest extends TestCase
{
    /**
     * @return array{0: NotificationService, 1: Swift_Transport_CapturingTransport}
     */
    private function createNotificationService(ArrayLogger $logger, ?Swift_Transport_CapturingTransport $transport = null): array
    {
        $baseInfo = new BaseInfo();
        $baseInfo->setEmail01('shop@example.com');
        $baseInfo->setShopName('テストショップ');

        $config = new Config();
        $config->setAdminTo('admin@example.com');

        $transport = $transport ?: new Swift_Transport_CapturingTransport();
        $loader = new FilesystemLoader([__DIR__ . '/../../']);

        $baseInfoRepository = $this->createMock(\Eccube\Repository\BaseInfoRepository::class);
        $baseInfoRepository->method('get')->willReturn($baseInfo);

        $configRepository = $this->createMock(\Plugin\CustomerChangeNotify\Repository\ConfigRepository::class);
        $configRepository->method('get')->willReturn($config);

        $service = new NotificationService(
            new Swift_Mailer($transport),
            new Environment($loader),
            $baseInfoRepository,
            $configRepository,
            new DiffBuilder(['email', 'name01', 'name02']),
            $logger
        );

        return [$service, $transport];
    }

    public function testOnFlushQueuesAndPostFlushSendsNotifications(): void
    {
        $logger = new ArrayLogger();
        $serviceLogger = new ArrayLogger();
        [$notificationService, $transport] = $this->createNotificationService($serviceLogger);

        $subscriber = new CustomerChangeSubscriber($notificationService, $logger);

        $customer = new Customer();
        $customer->setId(10);
        $customer->setEmail('old@example.com');
        $customer->setName01('山田');
        $customer->setName02('太郎');
        $customer->setEmail('new@example.com');

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityUpdates')->willReturn([$customer]);
        $uow->method('getEntityChangeSet')->with($customer)->willReturn([
            'email' => ['old@example.com', 'new@example.com'],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        $onFlushArgs = $this->createMock(OnFlushEventArgs::class);
        $onFlushArgs->method('getEntityManager')->willReturn($em);

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getEntityManager')->willReturn($em);

        $subscriber->onFlush($onFlushArgs);
        $subscriber->postFlush($postFlushArgs);

        $messages = $transport->messages();
        $this->assertCount(2, $messages);

        $infoLogs = $logger->filterByLevel('info');
        $this->assertNotEmpty($infoLogs);
        $this->assertStringContainsString('通知をキューに追加', $infoLogs[0]['message']);

        $this->assertNotEmpty($serviceLogger->filterByLevel('info'));
    }

    public function testPostFlushLogsErrorWhenNotifyFails(): void
    {
        $logger = new ArrayLogger();
        $diff = new Diff();
        $diff->addChange('email', 'メールアドレス', 'before@example.com', 'after@example.com', 'before@example.com', 'after@example.com');

        $notificationService = $this->createMock(NotificationService::class);
        $notificationService
            ->method('buildDiff')
            ->willReturn($diff);
        $notificationService
            ->method('notify')
            ->willThrowException(new \RuntimeException('failure'));

        $subscriber = new CustomerChangeSubscriber($notificationService, $logger);

        $customer = new Customer();
        $customer->setId(20);
        $customer->setEmail('before@example.com');
        $customer->setEmail('after@example.com');

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityUpdates')->willReturn([$customer]);
        $uow->method('getEntityChangeSet')->willReturn(['email' => ['before@example.com', 'after@example.com']]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        $onFlushArgs = $this->createMock(OnFlushEventArgs::class);
        $onFlushArgs->method('getEntityManager')->willReturn($em);

        $postFlushArgs = $this->createMock(PostFlushEventArgs::class);
        $postFlushArgs->method('getEntityManager')->willReturn($em);

        $subscriber->onFlush($onFlushArgs);
        $subscriber->postFlush($postFlushArgs);

        $errors = $logger->filterByLevel('error');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('通知処理でエラー発生', $errors[0]['message']);
    }

    public function testMultipleFlushesNotifyAllDetectedChanges(): void
    {
        $logger = new ArrayLogger();
        $notificationService = $this->createMock(NotificationService::class);
        $subscriber = new CustomerChangeSubscriber($notificationService, $logger);

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

        $notificationService
            ->method('buildDiff')
            ->willReturnOnConsecutiveCalls($diff1, $diff2);

        $expectedNotifyArgs = [
            [$customer1, $diff1],
            [$customer2, $diff2],
        ];
        $callIndex = 0;
        $notificationService
            ->expects($this->exactly(2))
            ->method('notify')
            ->with(
                $this->callback(function ($customer) use (&$callIndex, $expectedNotifyArgs) {
                    return $customer === $expectedNotifyArgs[$callIndex][0];
                }),
                $this->callback(function ($diff) use (&$callIndex, $expectedNotifyArgs) {
                    return $diff === $expectedNotifyArgs[$callIndex][1];
                }),
                $this->anything()
            )
            ->willReturnCallback(function () use (&$callIndex) {
                $callIndex++;
            });

        // 1 回目の flush
        $em1 = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $uow1 = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $em1->method('getUnitOfWork')->willReturn($uow1);
        $uow1->method('getScheduledEntityUpdates')->willReturn([$customer1]);
        $uow1->method('getEntityChangeSet')->willReturn(['email' => ['old1@example.com', 'new1@example.com']]);

        $onFlushArgs1 = $this->createMock(\Doctrine\ORM\Event\OnFlushEventArgs::class);
        $onFlushArgs1->method('getEntityManager')->willReturn($em1);

        $subscriber->onFlush($onFlushArgs1);
        $subscriber->postFlush($this->createMock(\Doctrine\ORM\Event\PostFlushEventArgs::class));

        // 2 回目の flush（同一リクエスト内）
        $em2 = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $uow2 = $this->createMock(\Doctrine\ORM\UnitOfWork::class);
        $em2->method('getUnitOfWork')->willReturn($uow2);
        $uow2->method('getScheduledEntityUpdates')->willReturn([$customer2]);
        $uow2->method('getEntityChangeSet')->willReturn(['email' => ['old2@example.com', 'new2@example.com']]);

        $onFlushArgs2 = $this->createMock(\Doctrine\ORM\Event\OnFlushEventArgs::class);
        $onFlushArgs2->method('getEntityManager')->willReturn($em2);

        $subscriber->onFlush($onFlushArgs2);
        $subscriber->postFlush($this->createMock(\Doctrine\ORM\Event\PostFlushEventArgs::class));
    }
}
