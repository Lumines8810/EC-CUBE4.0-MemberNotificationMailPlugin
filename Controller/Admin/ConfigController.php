<?php

namespace Plugin\CustomerChangeNotify\Controller\Admin;

use Eccube\Controller\AbstractController;
use Plugin\CustomerChangeNotify\Form\Type\ConfigType;
use Plugin\CustomerChangeNotify\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * 会員情報変更通知プラグインの管理画面コントローラー.
 */
class ConfigController extends AbstractController
{
    /**
     * 設定画面.
     *
     * @Route("/%eccube_admin_route%/customer_change_notify/config", name="customer_change_notify_admin_config")
     * @Template("@CustomerChangeNotify/CustomerChangeNotify/admin/config.twig")
     */
    public function index(Request $request, ConfigRepository $configRepository)
    {
        $Config = $configRepository->get();

        $form = $this->createForm(ConfigType::class, $Config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $Config = $form->getData();
            $this->entityManager->persist($Config);
            $this->entityManager->flush();

            $this->addSuccess('設定を保存しました。', 'admin');

            return $this->redirectToRoute('customer_change_notify_admin_config');
        }

        return [
            'form' => $form->createView(),
        ];
    }
}
