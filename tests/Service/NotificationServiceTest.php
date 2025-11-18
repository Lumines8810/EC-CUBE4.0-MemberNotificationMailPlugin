<?php

namespace Plugin\CustomerChangeNotify\Tests\Service;

use PHPUnit\Framework\TestCase;
use Plugin\CustomerChangeNotify\Entity\Config;
use Plugin\CustomerChangeNotify\Service\Diff;
use Plugin\CustomerChangeNotify\Service\DiffBuilder;
use Plugin\CustomerChangeNotify\Service\NotificationService;
use Plugin\CustomerChangeNotify\Tests\Fixtures\Logger\ArrayLogger;
use Swift_Mailer;
use Swift_Message;
use Swift_Transport_CapturingTransport;
use Swift_Mime_SimpleMessage;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Customer;
use Symfony\Component\HttpFoundation\Request;

class NotificationServiceTest extends TestCase
{
    /** @var NotificationService */
    private $service;

    /** @var Swift_Transport_CapturingTransport */
    private $transport;

    /** @var ArrayLogger */
    private $logger;

    protected function setUp(): void
    {
        $baseInfo = new BaseInfo();
        $baseInfo->setEmail01('shop@example.com');
        $baseInfo->setShopName('テストショップ');

        $config = new Config();
        $config->setAdminTo('admin@example.com');
        $config->setAdminSubject('管理者向け件名');
        $config->setMemberSubject('会員向け件名');

        $this->transport = new Swift_Transport_CapturingTransport();
        $mailer = new Swift_Mailer($this->transport);

        $loader = new FilesystemLoader([__DIR__ . '/../../Resource/template']);
        $twig = new Environment($loader);

        $diffBuilder = new DiffBuilder(['email', 'name01', 'name02']);
        $this->logger = new ArrayLogger();

        $baseInfoRepository = $this->createMock(\Eccube\Repository\BaseInfoRepository::class);
        $baseInfoRepository->method('get')->willReturn($baseInfo);

        $configRepository = $this->createMock(\Plugin\CustomerChangeNotify\Repository\ConfigRepository::class);
        $configRepository->method('get')->willReturn($config);

        $this->service = new NotificationService(
            $mailer,
            $twig,
            $baseInfoRepository,
            $configRepository,
            $diffBuilder,
            $this->logger
        );
    }

    public function testNotifyRendersTwigTemplatesAndSendsMail(): void
    {
        $customer = new Customer();
        $customer->setId(99);
        $customer->setEmail('member@example.com');
        $customer->setName01('山田');
        $customer->setName02('太郎');

        $diff = new Diff();
        $diff->addChange('email', 'メールアドレス', 'old@example.com', 'member@example.com', 'old@example.com', 'member@example.com');

        $this->service->notify($customer, $diff, new Request());

        $messages = $this->transport->messages();
        $this->assertCount(2, $messages);

        /** @var Swift_Message $admin */
        $admin = $messages[0];
        /** @var Swift_Message $member */
        $member = $messages[1];

        $this->assertSame('管理者向け件名', $admin->getSubject());
        $this->assertSame('会員向け件名', $member->getSubject());

        $this->assertStringContainsString('山田 太郎 様の会員情報が変更されました。', $admin->getBody());
        $this->assertStringContainsString('- メールアドレス:', $admin->getBody());
        $this->assertStringContainsString('会員情報が変更されましたのでお知らせいたします。', $member->getBody());
        $this->assertStringContainsString('- メールアドレス が変更されました。', $member->getBody());
    }

    public function testNotifySkipsEmptyDiff(): void
    {
        $customer = new Customer();
        $customer->setId(1);
        $customer->setEmail('nochange@example.com');

        $diff = new Diff();

        $this->service->notify($customer, $diff, null);

        $this->assertEmpty($this->transport->messages());
    }

    public function testNotifyLogsErrorsFromMailer(): void
    {
        $customer = new Customer();
        $customer->setId(2);
        $customer->setEmail('member@example.com');

        $diff = new Diff();
        $diff->addChange('email', 'メールアドレス', 'old@example.com', 'member@example.com', 'old@example.com', 'member@example.com');

        $throwingTransport = new class extends \Swift_Transport_AbstractTransport {
            public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
            {
                throw new \RuntimeException('transport failed');
            }

            public function ping(): bool
            {
                return false;
            }
        };

        $ref = new \ReflectionProperty(NotificationService::class, 'mailer');
        $ref->setAccessible(true);
        $ref->setValue($this->service, new Swift_Mailer($throwingTransport));

        $this->service->notify($customer, $diff);

        $errors = $this->logger->filterByLevel('error');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('メール送信エラー', $errors[0]['message']);
    }
}
