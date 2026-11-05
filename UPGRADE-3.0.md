# UPGRADE FROM `2.2` TO `3.0`

## Breaking changes

1. **The PayPal fields on the admin payment method form moved to section hooks.**

   The fields used to be hookables of one flat hook. They now live in nested hooks, one per section and group.
   Every field keeps its own hookable and template, so you can still override or disable a single field, but
   under its new hook name. An override that still targets the old hook does nothing and reports no error.

   In the tables below, hook names are shortened to the part from `sylius_paypal` on. The full name starts with
   `sylius_admin.payment_method.update.content.form.sections.gateway_configuration.`.

   The `sylius_paypal` hook now holds only two section hookables, `payment_methods` and `configuration`.

   **Payment methods section**

   | Hookable                     | Old hook        | New hook                                      |
   |------------------------------|-----------------|-----------------------------------------------|
   | `pay_later_enabled`          | `sylius_paypal` | `sylius_paypal.payment_methods.paypal_wallet` |
   | `messaging_enabled`          | `sylius_paypal` | `sylius_paypal.payment_methods.paypal_wallet` |
   | `card_three_d_secure_always` | `sylius_paypal` | `sylius_paypal.payment_methods.cards`         |
   | `venmo_enabled`              | `sylius_paypal` | `sylius_paypal.payment_methods.venmo`         |
   | `google_pay_enabled`         | `sylius_paypal` | `sylius_paypal.payment_methods.google_pay`    |
   | `apple_pay_enabled`          | `sylius_paypal` | `sylius_paypal.payment_methods.apple_pay`     |
   | `trustly_enabled`            | `sylius_paypal` | `sylius_paypal.payment_methods.trustly`       |

   **Configuration section**

   | Hookable                   | Old hook        | New hook                               |
   |----------------------------|-----------------|----------------------------------------|
   | `client_id`                | `sylius_paypal` | `sylius_paypal.configuration.account`  |
   | `client_secret`            | `sylius_paypal` | `sylius_paypal.configuration.account`  |
   | `express_checkout_enabled` | `sylius_paypal` | `sylius_paypal.configuration.checkout` |
   | `reports_sftp_username`    | `sylius_paypal` | `sylius_paypal.configuration.reports`  |
   | `reports_sftp_password`    | `sylius_paypal` | `sylius_paypal.configuration.reports`  |

   New hookables you can override or disable the same way:

   - `sylius_paypal.payment_methods`: `paypal_wallet`, `cards`, `venmo`, `google_pay`, `apple_pay`, `trustly`
     (the payment method tiles);
   - `sylius_paypal.configuration`: `account`, `checkout`, `reports` (the configuration groups);
   - `sylius_paypal.configuration.account`: `mode`, `merchant_id` (read-only values).

   To migrate, move each override to its new hook. For example, this hides the Venmo switch in `2.2`:

   ```yaml
   sylius_twig_hooks:
       hooks:
           'sylius_admin.payment_method.update.content.form.sections.gateway_configuration.sylius_paypal':
               venmo_enabled:
                   enabled: false
   ```

   In `3.0` it becomes the following. To hide the whole Venmo tile, disable the `venmo` hookable of
   `sylius_paypal.payment_methods` instead:

   ```yaml
   sylius_twig_hooks:
       hooks:
           'sylius_admin.payment_method.update.content.form.sections.gateway_configuration.sylius_paypal.payment_methods.venmo':
               venmo_enabled:
                   enabled: false
   ```
