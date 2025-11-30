<?php

namespace Plugin\CustomerChangeNotify\Form\Type;

use Plugin\CustomerChangeNotify\Entity\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 会員情報変更通知プラグインの設定フォーム.
 */
class ConfigType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('admin_to', EmailType::class, [
                'label' => '管理者通知先メールアドレス',
                'required' => false,
                'constraints' => [
                    new Assert\Email([
                        'message' => '有効なメールアドレスを入力してください。',
                    ]),
                ],
                'attr' => [
                    'placeholder' => '例: admin@example.com',
                    'help' => '空欄の場合は店舗設定のメールアドレスが使用されます。',
                ],
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }
}
