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
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class CustomerChangeSubscriberTest extends TestCase
{
    private function createNotificationService(ArrayLogger $logger): NotificationService
    {
        $baseInfo = new BaseInfo();
        $baseInfo->setEmail01('shop@example.com');
        $baseInfo->setShopName('テストショップ');

        $config = new Config();
        $config->setAdminTo('admin@example.com');

        $transport = new Swift_Transport_CapturingTransport();
        $loader = new FilesystemLoader([__DIR__ . '/../../Resource/template']);

        $baseInfoRepository = $this->createMock(\Eccube\Repository\BaseInfoRepository::class);
        $baseInfoRepository->method('get')->willReturn($baseInfo);

        $configRepository = $this->createMock(\Plugin\CustomerChangeNotify\Repository\ConfigRepository::class);
        $configRepository->method('get')->willReturn($config);

        return new NotificationService(
            new Swift_Mailer($transport),
            new Environment($loader),
            $baseInfoRepository,
            $configRepository,
            new DiffBuilder(['email', 'name01', 'name02']),
            $logger
        );
    }

    public function testOnFlushQueuesAndPostFlushSendsNotifications(): void
    {
        $logger = new ArrayLogger();
        $serviceLogger = new ArrayLogger();
        $notificationService = $this->createNotificationService($serviceLogger);
        $transport = new Swift_Transport_CapturingTransport();
        // 差し替え
        $ref = new \ReflectionProperty(NotificationService::class, 'mailer');
        $ref->setAccessible(true);
        $ref->setValue($notificationService, new Swift_Mailer($transport));

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $subscriber = new CustomerChangeSubscriber($notificationService, $requestStack, $logger);

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
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $baseService = $this->createNotificationService($logger);

        $failingService = new class($baseService) extends NotificationService {
            public function __construct(NotificationService $baseService)
            {
                $reflection = new \ReflectionClass(NotificationService::class);

                $mailer = $reflection->getProperty('mailer');
                $mailer->setAccessible(true);

                $twig = $reflection->getProperty('twig');
                $twig->setAccessible(true);

                $baseInfoRepository = $reflection->getProperty('baseInfoRepository');
                $baseInfoRepository->setAccessible(true);

                $configRepository = $reflection->getProperty('configRepository');
                $configRepository->setAccessible(true);

                $diffBuilder = $reflection->getProperty('diffBuilder');
                $diffBuilder->setAccessible(true);

                $logger = $reflection->getProperty('logger');
                $logger->setAccessible(true);

                parent::__construct(
                    $mailer->getValue($baseService),
                    $twig->getValue($baseService),
                    $baseInfoRepository->getValue($baseService),
                    $configRepository->getValue($baseService),
                    $diffBuilder->getValue($baseService),
                    $logger->getValue($baseService)
                );
            }

            public function notify(\Eccube\Entity\Customer $customer, Diff $diff, ?Request $request = null): void
            {
                throw new \RuntimeException('failure');
            }
        };

        $subscriber = new CustomerChangeSubscriber($failingService, $requestStack, $logger);

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
        $requestStack = $this->createMock(RequestStack::class);
        $subscriber = new CustomerChangeSubscriber($notificationService, $requestStack, $logger);

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

        $requestStack
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
