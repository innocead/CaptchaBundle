<?php

namespace Innocead\CaptchaBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CaptchaController extends Controller
{

    public function captchaAction($random)
    {
        $captcha = $this->get('innocead_captcha');

        return $captcha->generateResponse();
    }

}
