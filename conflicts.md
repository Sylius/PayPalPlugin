# Dependency conflicts

This file documents `composer.json` `conflict` entries that exist purely to work around a
temporary upstream regression, so it's clear when they can be removed again.

## `twig/twig: 3.29.*`

`twig/twig` 3.29.0 made `Twig\TemplateWrapper::unwrap()` require an `Environment $env` argument
(previously it took none). `sylius/mailer-bundle` 2.2.0's `EmailTwigAdapter::provideEmailWithTemplate()`
still calls `->unwrap()` with no arguments, so any order-confirmation email send fails with:

```
Type error: Too few arguments to function Twig\TemplateWrapper::unwrap(), 0 passed in
vendor/sylius/mailer-bundle/src/Bundle/Renderer/Adapter/EmailTwigAdapter.php on line 64 and
exactly 1 expected in vendor/twig/twig/src/TemplateWrapper.php:110
```

Since this repo doesn't commit `composer.lock`, every CI run re-resolves the newest matching
`twig/twig` release, so this broke CI (Symfony ^6.4 combinations) as soon as 3.29.0 was published,
independent of any code change in this repo.

Remove this conflict once `sylius/mailer-bundle` ships a release that calls `unwrap($environment)`
correctly (or once it's confirmed a later `twig/twig` 3.29.x patch fixes the call site instead).
