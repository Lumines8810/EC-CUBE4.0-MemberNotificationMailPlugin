<?php

namespace Plugin\CustomerChangeNotify\Tests\Service;

use PHPUnit\Framework\TestCase;
use Plugin\CustomerChangeNotify\Repository\ConfigRepository;
use Plugin\CustomerChangeNotify\Service\NotificationService;
use Plugin\CustomerChangeNotify\Service\DiffBuilder;
use Plugin\CustomerChangeNotify\Service\Diff;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../Service/NotificationService.php';
require_once __DIR__ . '/../../Service/DiffBuilder.php';

/**
 * NotificationService のユニットテスト.
 *
 * 注: このテストは基本的な構造を提供します。
 * EC-CUBE の完全な環境が必要な統合テストは別途作成してください。
 */
class NotificationServiceTest extends TestCase
{
    /**
     * @var \Swift_Mailer|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mailer;

    /**
     * @var \Twig_Environment|\PHPUnit\Framework\MockObject\MockObject
     */
    private $twig;

    /**
     * @var \Eccube\Repository\BaseInfoRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    private $baseInfoRepository;

    /**
     * @var ConfigRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    private $configRepository;

    /**
     * @var DiffBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    private $diffBuilder;

    /**
     * @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $logger;

    /**
     * @var NotificationService
     */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock オブジェクトの作成
        $this->mailer = $this->createMock(\Swift_Mailer::class);
        $this->twig = $this->createMock(\Twig_Environment::class);
        $this->baseInfoRepository = $this->createMock(\Eccube\Repository\BaseInfoRepository::class);
        $this->configRepository = $this->createMock(ConfigRepository::class);
        $this->diffBuilder = $this->createMock(DiffBuilder::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // NotificationService のインスタンス作成
        $this->service = new NotificationService(
            $this->mailer,
            $this->twig,
            $this->baseInfoRepository,
            $this->configRepository,
            $this->diffBuilder,
            $this->logger
        );
    }

    /**
     * buildDiff メソッドが DiffBuilder に委譲することを確認.
     */
    public function testBuildDiffDelegatesToDiffBuilder(): void
    {
        $customer = $this->createMock(\Eccube\Entity\Customer::class);
        $customer->method('getId')->willReturn(123);
        $customer->method('getEmail')->willReturn('test@example.com');

        $changeSet = ['email' => ['old@example.com', 'new@example.com']];
        $diff = new Diff();
        $diff->addChange('email', 'メールアドレス', 'old@example.com', 'new@example.com', 'old@example.com', 'new@example.com');

        $this->diffBuilder
            ->expects($this->once())
            ->method('build')
            ->with($customer, $changeSet)
            ->willReturn($diff);

        // ログ記録の確認
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('[CustomerChangeNotify] 会員情報の変更を検知'),
                $this->arrayHasKey('customer_id')
            );

        $result = $this->service->buildDiff($customer, $changeSet);

        $this->assertSame($diff, $result);
        $this->assertFalse($result->isEmpty());
    }

    /**
     * buildDiff で差分が空の場合、ログが記録されないことを確認.
     */
    public function testBuildDiffWithEmptyDiffDoesNotLog(): void
    {
        $customer = $this->createMock(\Eccube\Entity\Customer::class);
        $changeSet = [];
        $emptyDiff = new Diff();

        $this->diffBuilder
            ->expects($this->once())
            ->method('build')
            ->with($customer, $changeSet)
            ->willReturn($emptyDiff);

        // ログが記録されないことを確認
        $this->logger
            ->expects($this->never())
            ->method('info');

        $result = $this->service->buildDiff($customer, $changeSet);

        $this->assertTrue($result->isEmpty());
    }

    /**
     * notify メソッドが空の差分で何もしないことを確認.
     */
    public function testNotifyWithEmptyDiffDoesNothing(): void
    {
        $customer = $this->createMock(\Eccube\Entity\Customer::class);
        $emptyDiff = new Diff();

        // メール送信が呼ばれないことを確認
        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->service->notify($customer, $emptyDiff);
    }

    /**
     * notify メソッドのエラーハンドリングを確認.
     *
     * 注: 完全なテストには EC-CUBE の BaseInfo エンティティのモックが必要です。
     * このテストは基本的な構造を示すものです。
     */
    public function testNotifyHandlesExceptionsGracefully(): void
    {
        $customer = $this->createMock(\Eccube\Entity\Customer::class);
        $customer->method('getId')->willReturn(456);
        $customer->method('getEmail')->willReturn('customer@example.com');

        $diff = new Diff();
        $diff->addChange('email', 'メールアドレス', 'old@example.com', 'new@example.com', 'old@example.com', 'new@example.com');

        // BaseInfoRepository がエラーをスローする場合
        $this->baseInfoRepository
            ->method('get')
            ->willThrowException(new \RuntimeException('Database error'));

        // エラーログが記録されることを確認
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                $this->stringContains('[CustomerChangeNotify]'),
                $this->anything()
            );

        // 例外が外部に伝播しないことを確認
        $this->service->notify($customer, $diff);
        $this->assertTrue(true); // 例外がスローされなければテスト成功
    }
}
