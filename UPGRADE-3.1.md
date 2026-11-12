# UPGRADE FROM 3.0 to 3.1

1. #### The following signatures have gained new optional arguments.

   ```diff
    // Sylius\PayPalPlugin\Controller\AddToCartAction
    // Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromCartAction
    // Sylius\PayPalPlugin\Controller\PayPalButtonsController
    public function __construct(
        ...
   +    ?PayPalVaultRequestProviderInterface $vaultRequestProvider = null,
    )
   ```

   Not passing `Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface` is deprecated since 3.1 and it will
   be required in 4.0.
