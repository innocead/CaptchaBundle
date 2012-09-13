<?php

namespace Innocead\CaptchaBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Innocead\CaptchaBundle\Captcha as BaseCaptcha;

class CaptchaValidator extends ConstraintValidator
{

    private $captcha;

    public function __construct(BaseCaptcha $captcha)
    {
        $this->captcha = $captcha;
    }

    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string)$value;

        if (!$this->captcha->isValid($value)) {
            $this->context->addViolation($constraint->message);
        }
    }

}
