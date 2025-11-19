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
use Swift_Mime_SimpleMessage;
use Swift_Transport_CapturingTransport;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
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

    /** @var \Eccube\Repository\BaseInfoRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $baseInfoRepository;

    /** @var \Plugin\CustomerChangeNotify\Repository\ConfigRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $configRepository;

    /** @var DiffBuilder */
    private $diffBuilder;

    /** @var Environment */
    private $twig;

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
        $this->logger = new ArrayLogger();
        $this->diffBuilder = new DiffBuilder(['email', 'name01', 'name02']);

        $loader = new ArrayLoader([
            '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_admin_mail.twig' => '管理者向け: {{ Customer.getName01() }} {{ Customer.getName02() }} {{ diff|length }}件',
            '@CustomerChangeNotify/CustomerChangeNotify/Mail/customer_change_member_mail.twig' => '会員向け:{% for change in diff %}- {{ change.label }} が変更されました。{% endfor %}',
        ]);
        $this->twig = new Environment($loader);

        $this->baseInfoRepository = $this->createMock(\Eccube\Repository\BaseInfoRepository::class);
        $this->baseInfoRepository->method('get')->willReturn($baseInfo);

        $this->configRepository = $this->createMock(\Plugin\CustomerChangeNotify\Repository\ConfigRepository::class);
        $this->configRepository->method('get')->willReturn($config);

        $this->service = $this->createService(new Swift_Mailer($this->transport));
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

        $this->assertStringContainsString('管理者向け: 山田 太郎 1件', $admin->getBody());
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

        $failingService = $this->createService(new Swift_Mailer($throwingTransport));
        $failingService->notify($customer, $diff);

        $errors = $this->logger->filterByLevel('error');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('メール送信エラー', $errors[0]['message']);
    }

    private function createService(Swift_Mailer $mailer): NotificationService
    {
        return new NotificationService(
            $mailer,
            $this->twig,
            $this->baseInfoRepository,
            $this->configRepository,
            $this->diffBuilder,
            $this->logger
        );
    }
}
