### UPGRADE FROM 1.5.1 to 1.6

1. Support for Sylius 1.13 has been added, it is now the recommended Sylius version to use.
2. Support for PHP 8.0 has been dropped.

### UPGRADE FROM 1.3.0 to 1.3.1

1. `sylius_paypal_plugin_pay_with_paypal_form` route now operates on both payment ID and order token. URl then changed from
   `/pay-with-paypal/{id}` to `/pay-with-paypal/{orderToken}/{paymentId}`. If you use this route anywhere in your application, you
   need to change the URL attributes

### UPGRADE FROM 1.2.3 to 1.2.4

1. `sylius_paypal_plugin_pay_with_paypal_form` route now operates on both payment ID and order token. URl then changed from
    `/pay-with-paypal/{id}` to `/pay-with-paypal/{orderToken}/{paymentId}`. If you use this route anywhere in your application, you
    need to change the URL attributes

### UPGRADE FROM 1.0.X TO 1.1.0

1. Upgrade your application to [Sylius 1.8](https://github.com/Sylius/Sylius/blob/master/UPGRADE-1.8.md).

1. Remove previously copied migration files (You may check migrations to remove [here](https://github.com/Sylius/PayPalPlugin/pull/160/files)).
