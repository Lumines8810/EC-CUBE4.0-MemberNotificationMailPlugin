<?php

namespace Plugin\CustomerChangeNotify\Tests\Event;

use PHPUnit\Framework\TestCase;
use Plugin\CustomerChangeNotify\Entity\Config;
use Plugin\CustomerChangeNotify\Event\CustomerChangeSubscriber;
use Plugin\CustomerChangeNotify\Service\Diff;
use Plugin\CustomerChangeNotify\Service\DiffBuilder;
use Plugin\CustomerChangeNotify\Service\NotificationService;
use Plugin\CustomerChangeNotify\Tests\Fixtures\Logger\ArrayLogger;
use Plugin\CustomerChangeNotify\Tests\Fixtures\Repository\BaseInfoRepositoryStub;
use Plugin\CustomerChangeNotify\Tests\Fixtures\Repository\ConfigRepositoryStub;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Customer;
use Swift_Mailer;
use Swift_Transport_CapturingTransport;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig_Environment;
use Twig_Loader_Filesystem;

require_once __DIR__ . '/../../Event/CustomerChangeSubscriber.php';
require_once __DIR__ . '/../../Service/NotificationService.php';
require_once __DIR__ . '/../../Service/DiffBuilder.php';
require_once __DIR__ . '/../../Service/Diff.php';

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
        $loader = new Twig_Loader_Filesystem([__DIR__ . '/../../Resource/template']);

        return new NotificationService(
            new Swift_Mailer($transport),
            new Twig_Environment($loader),
            new BaseInfoRepositoryStub($baseInfo),
            new ConfigRepositoryStub($config),
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

        $em = new EntityManager();
        $customer = new Customer();
        $customer->setId(10);
        $customer->setEmail('old@example.com');
        $customer->setName01('山田');
        $customer->setName02('太郎');

        $em->persist($customer);
        $em->getUnitOfWork()->takeSnapshot();

        $customer->setEmail('new@example.com');
        $em->flush();

        $subscriber->onFlush(new OnFlushEventArgs($em));
        $subscriber->postFlush(new PostFlushEventArgs($em));

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

        $em = new EntityManager();
        $customer = new Customer();
        $customer->setId(20);
        $customer->setEmail('before@example.com');
        $em->persist($customer);
        $em->getUnitOfWork()->takeSnapshot();

        $customer->setEmail('after@example.com');
        $em->flush();

        $subscriber->onFlush(new OnFlushEventArgs($em));
        $subscriber->postFlush(new PostFlushEventArgs($em));

        $errors = $logger->filterByLevel('error');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('通知処理でエラー発生', $errors[0]['message']);
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

        $expectedNotifyArgs = [
            [$customer1, $diff1],
            [$customer2, $diff2],
        ];
        $callIndex = 0;
        $this->notificationService
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
