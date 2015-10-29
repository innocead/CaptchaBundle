# Installation

***Using Composer***

Use composer require to get the recent version.

``` bash
$ php composer.phar require innocead/captcha-bundle
```

Enable the bundle:

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Innocead\CaptchaBundle\InnoceadCaptchaBundle(),
    );
}
```
