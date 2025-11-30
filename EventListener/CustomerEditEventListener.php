<?php

namespace Plugin\CustomerChangeNotify\EventListener;

use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Entity\Customer;
use Plugin\CustomerChangeNotify\Service\NotificationService;
use Plugin\CustomerChangeNotify\Service\DiffBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

/**
 * EC-CUBE 標準の Symfony EventSubscriber.
 * 管理画面やマイページでの Customer 編集完了時にメール通知を送信する.
 *
 * Doctrine EventSubscriber ではなく Symfony EventSubscriber を使用することで
 * Segmentation fault を回避し、EC-CUBE の標準パターンに準拠します.
 */
class CustomerEditEventListener implements EventSubscriberInterface
{
    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * @var DiffBuilder
     */
    private $diffBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * 変更前の Customer を保存（INITIALIZE で保存、COMPLETE で比較）
     * @var Customer|null
     */
    private $originalCustomer = null;

    public function __construct(
        NotificationService $notificationService,
        DiffBuilder $diffBuilder,
        LoggerInterface $logger
    ) {
        $this->notificationService = $notificationService;
        $this->diffBuilder = $diffBuilder;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            EccubeEvents::ADMIN_CUSTOMER_EDIT_INDEX_INITIALIZE => 'onInitialize',
            EccubeEvents::ADMIN_CUSTOMER_EDIT_INDEX_COMPLETE => 'onComplete',
            EccubeEvents::FRONT_MYPAGE_CHANGE_INDEX_INITIALIZE => 'onInitialize',
            EccubeEvents::FRONT_MYPAGE_CHANGE_INDEX_COMPLETE => 'onComplete',
        ];
    }

    /**
     * Customer 編集フォーム表示時：変更前の Customer をコピーして保存.
     *
     * @param EventArgs $event
     */
    public function onInitialize(EventArgs $event): void
    {
        $Customer = $event->getArgument('Customer');

        if (!$Customer instanceof Customer || !$Customer->getId()) {
            $this->originalCustomer = null;
            return;
        }

        // 編集対象の Customer を clone して保存（変更前の状態）
        $this->originalCustomer = clone $Customer;

        $this->logger->debug('[CustomerChangeNotify] Customer 編集を開始', [
            'customer_id' => $Customer->getId(),
            'customer_email' => $Customer->getEmail(),
        ]);
    }

    /**
     * Customer 編集完了時：差分を検知してメール送信.
     *
     * 重要: COMPLETE 系イベントはいずれも EntityManager::flush() 実行後に発火するため、
     * ここで安全にクエリを実行したりメール送信できます.
     *
     * @param EventArgs $event
     */
    public function onComplete(EventArgs $event): void
    {
        $Customer = $event->getArgument('Customer');

        // 新規作成時は通知不要
        if (!$this->originalCustomer || !$Customer instanceof Customer) {
            return;
        }

        try {
            // 変更前後の値を比較して ChangeSet を構築
            $changeSet = $this->buildChangeSet($this->originalCustomer, $Customer);

            if (empty($changeSet)) {
                $this->logger->debug('[CustomerChangeNotify] Customer の変更フィールドなし');
                return;
            }

            // DiffBuilder を使用して監視対象フィールドのみ抽出
            $diff = $this->diffBuilder->build($Customer, $changeSet);

            if ($diff->isEmpty()) {
                $this->logger->debug('[CustomerChangeNotify] 監視対象フィールドの変更なし', [
                    'customer_id' => $Customer->getId(),
                ]);
                return;
            }

            $this->logger->info('[CustomerChangeNotify] 会員情報の変更を検知', [
                'customer_id' => $Customer->getId(),
                'customer_email' => $Customer->getEmail(),
                'changed_fields' => array_keys($diff->getChanges()),
            ]);

            $this->notificationService->notify($Customer, $diff, $this->resolveRequest($event));

        } catch (\Exception $e) {
            $this->logger->error('[CustomerChangeNotify] 通知処理でエラー発生', [
                'customer_id' => $Customer->getId() ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // エラーが発生しても、Customer 編集フローには影響を与えない
        } finally {
            // イベント完了後、元のオブジェクトをクリア
            $this->originalCustomer = null;
        }
    }

    /**
     * 変更前後の Customer オブジェクトから ChangeSet を生成.
     * Doctrine 形式の ChangeSet: ['field' => [old, new], ...]
     *
     * @param Customer $original 変更前
     * @param Customer $current  変更後
     * @return array
     */
    private function buildChangeSet(Customer $original, Customer $current): array
    {
        $changeSet = [];

        // 監視対象フィールド（services.yaml と同じリスト）
        $fields = [
            'name01', 'name02', 'kana01', 'kana02',
            'email', 'tel01', 'tel02', 'tel03',
            'zip01', 'zip02', 'addr01', 'addr02',
        ];

        foreach ($fields as $field) {
            $getter = 'get' . ucfirst($field);

            // フィールドが存在しない場合はスキップ
            if (!method_exists($original, $getter)) {
                continue;
            }

            $oldValue = $original->$getter();
            $newValue = $current->$getter();

            // 値が異なれば ChangeSet に追加
            if ($oldValue !== $newValue) {
                $changeSet[$field] = [$oldValue, $newValue];
            }
        }

        return $changeSet;
    }

    private function resolveRequest(EventArgs $event): ?Request
    {
        $request = $event->getRequest();

        return $request instanceof Request ? $request : null;
    }
}
