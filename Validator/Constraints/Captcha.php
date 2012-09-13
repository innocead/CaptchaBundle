<?php

namespace Innocead\CaptchaBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 *
 * @api
 */
class Captcha extends Constraint
{

    public $message = 'innocead_captcha.captcha.message';

    public function validatedBy()
    {
        return 'innocead_captcha.validator';
    }

}
