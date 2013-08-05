# Installation

***Using Composer***

Add the following to the "require" section of your `composer.json` file:

```
    "innocead/captcha-bundle": "dev-master"
```

And update your dependencies

``` bash
$ php composer.phar update innocead/captcha-bundle
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
