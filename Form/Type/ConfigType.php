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
            ])
            ->add('admin_subject', TextType::class, [
                'label' => '管理者向けメール件名',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'メール件名を入力してください。',
                    ]),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'メール件名は{{ limit }}文字以内で入力してください。',
                    ]),
                ],
                'attr' => [
                    'placeholder' => '例: 会員情報変更通知（管理者向け）',
                ],
            ])
            ->add('member_subject', TextType::class, [
                'label' => '会員向けメール件名',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'メール件名を入力してください。',
                    ]),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'メール件名は{{ limit }}文字以内で入力してください。',
                    ]),
                ],
                'attr' => [
                    'placeholder' => '例: 会員情報が変更されました',
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
