# CONFLICTS

This document explains why certain conflicts were added to `composer.json` and references related issues.

- `twig/twig:3.29.*`:

  This version made `Twig\TemplateWrapper::unwrap()` require an `Environment $env` argument (previously it took none).
  `sylius/mailer-bundle` 2.2.0's `EmailTwigAdapter::provideEmailWithTemplate()` still calls it with no arguments, so
  sending any order-confirmation email throws `Too few arguments to function Twig\TemplateWrapper::unwrap(), 0 passed
  ... and exactly 1 expected`.
