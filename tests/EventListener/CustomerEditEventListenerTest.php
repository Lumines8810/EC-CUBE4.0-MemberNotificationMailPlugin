<?php

namespace Plugin\CustomerChangeNotify\Tests\EventListener;

use Eccube\Entity\Customer;
use Eccube\Event\EventArgs;
use PHPUnit\Framework\TestCase;
use Plugin\CustomerChangeNotify\EventListener\CustomerEditEventListener;
use Plugin\CustomerChangeNotify\Service\DiffBuilder;
use Plugin\CustomerChangeNotify\Service\NotificationService;
use Plugin\CustomerChangeNotify\Tests\Fixtures\Logger\ArrayLogger;
use Symfony\Component\HttpFoundation\Request;

class CustomerEditEventListenerTest extends TestCase
{
    public function testFrontMypageCompleteSendsNotificationWithRequest(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $diffBuilder = new DiffBuilder(['email'], ['email' => 'メールアドレス']);
        $listener = new CustomerEditEventListener($notificationService, $diffBuilder, new ArrayLogger());

        $Customer = new Customer();
        $Customer->setId(1);
        $Customer->setEmail('old@example.com');

        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $event = new EventArgs(['Customer' => $Customer], $request);

        $listener->onInitialize($event);

        $Customer->setEmail('new@example.com');

        $notificationService
            ->expects($this->once())
            ->method('notify')
            ->with(
                $this->identicalTo($Customer),
                $this->callback(function ($diff) {
                    return $diff instanceof \Plugin\CustomerChangeNotify\Service\Diff
                        && isset($diff->getChanges()['email']);
                }),
                $this->identicalTo($request)
            );

        $listener->onComplete($event);
    }

    public function testNoNotificationWhenChangeSetEmpty(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $diffBuilder = new DiffBuilder(['email'], ['email' => 'メールアドレス']);
        $listener = new CustomerEditEventListener($notificationService, $diffBuilder, new ArrayLogger());

        $Customer = new Customer();
        $Customer->setId(99);
        $Customer->setEmail('same@example.com');

        $event = new EventArgs(['Customer' => $Customer], new Request());
        $listener->onInitialize($event);

        $notificationService->expects($this->never())->method('notify');

        $listener->onComplete($event);
    }
}
