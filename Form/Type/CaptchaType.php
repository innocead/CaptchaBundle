<?php

namespace Innocead\CaptchaBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Innocead\CaptchaBundle\Form\EventSubscriver\CaptchaSubscriber;

class CaptchaType extends AbstractType
{
    private $captchaWidth;
    private $captchaHeight;

    public function __construct(array $captchaOptions)
    {
        $this->captchaWidth = $captchaOptions['width'];
        $this->captchaHeight = $captchaOptions['height'];
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['value'] = '';
        $view->vars['captcha_width'] = $this->captchaWidth;
        $view->vars['captcha_height'] = $this->captchaHeight;
        $view->vars['captcha_alt'] = isset($options['captcha_alt']) ? $options['captcha_alt'] : 'innocead_captcha.captcha.alt';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'required' => true,
                'attr' => array('class' => 'captcha_input')
            )
        );
    }

    public function getParent()
    {
        return 'text';
    }

    public function getName()
    {
        return 'innocead_captcha';
    }

}
