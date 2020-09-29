## Processing payments

Plugin provides `sylius:pay-pal-plugin:complete-payments` command, that should be configured as cron job on the server.
It iterates over processing PayPal payments and completes them if the order is completed in PayPal.
