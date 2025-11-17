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
}
