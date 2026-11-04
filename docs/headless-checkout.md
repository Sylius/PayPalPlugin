# Headless checkout with PayPal

A headless storefront pays with PayPal through the Sylius shop API alone. It places the order with the regular checkout
endpoints and pays it with a Payment Request:

1. `GET /api/v2/shop/orders/{tokenValue}/payments/{paymentId}/configuration` returns the PayPal Web SDK configuration.
2. `POST /api/v2/shop/orders/{tokenValue}/payment-requests` creates the PayPal order.
3. The payer approves the PayPal order in the SDK, or at the bank for a redirect payment source.
4. `PUT /api/v2/shop/payment-requests/{hash}` captures or authorizes the approved PayPal order.

## Configuration

Payment sources that leave the storefront, such as Trustly, send the payer back to the `return_url` of the PayPal
order. Point it to the storefront; `{hash}` is replaced with the Payment Request hash:

```yaml
sylius_paypal:
    shop_api:
        return_url: 'https://storefront.example.com/paypal/return/{hash}'
```

The URL is one value for all channels and locales. Without it, the payer returns to the Sylius shop.

## 1. Load the PayPal Web SDK

Once the order is completed and its payment uses a PayPal payment method, read the SDK configuration:

```http
GET /api/v2/shop/orders/{tokenValue}/payments/{paymentId}/configuration
```

```json
{
    "webSdkScriptUrl": "https://www.paypal.com/web-sdk/v6/core",
    "webSdkInstanceConfig": {
        "clientId": "...",
        "components": ["paypal-payments", "card-fields"],
        "pageType": "checkout",
        "partnerAttributionId": "...",
        "locale": "en-US"
    },
    "amount": "5.20",
    "currency": "USD",
    "countryCode": "US",
    "languageCode": "en",
    "sandbox": false,
    "paymentSources": ["paypal", "card", "trustly"],
    "payLaterEnabled": true,
    "messagingInstanceConfig": null
}
```

Load `webSdkScriptUrl`, create the SDK instance with `webSdkInstanceConfig` and render the payment sources listed in
`paymentSources`: these are the `payment_source` values step 2 accepts for this payment, including the redirect payment
sources the payer can use for the order's billing address. Show the Pay Later button when `payLaterEnabled` is true,
and the Pay Later messages with `messagingInstanceConfig` when it is not `null`.

## 2. Create the PayPal order

```http
POST /api/v2/shop/orders/{tokenValue}/payment-requests
Content-Type: application/ld+json

{
    "paymentId": 42,
    "paymentMethodCode": "PAYPAL",
    "payload": {"payment_source": "paypal"}
}
```

`payment_source` is one of the `paymentSources` from step 1: `paypal` (also for Pay Later), `venmo`, `card`,
`google_pay`, `apple_pay` or a redirect payment source. The action defaults to `capture`, or to `authorize` when the
payment method authorizes payments; any other action is refused with `422`.

```json
{
    "hash": "0199a0b4-...",
    "state": "processing",
    "responseData": {"paypal_order_id": "5O190127TN364715T"}
}
```

Return `responseData.paypal_order_id` from the SDK's `createOrder` callback. Keep `hash` for the next step.

## 3. Let the payer approve

| Payment source | Approval |
|---|---|
| `paypal`, `venmo` | The payer approves in the PayPal, Pay Later or Venmo popup of the SDK; continue in `onApprove`. |
| `card` | Submit the card fields with the PayPal order id; the SDK runs 3D Secure. Continue once it reports success. |
| `google_pay`, `apple_pay` | Confirm the order in the SDK; continue once it succeeds. |
| Redirect, e.g. `trustly` | Send the payer to `responseData.payer_action_url`. PayPal sends them back to the configured `return_url`. |

A payer who cancels at the bank comes back to the `return_url` with `payer_cancelled=1` added to its query string.
Let them choose how to pay again and create a new Payment Request (step 2).

## 4. Capture or authorize the payment

```http
PUT /api/v2/shop/payment-requests/{hash}
Content-Type: application/ld+json

{}
```

The plugin reads the PayPal order and captures or authorizes it. Read the outcome from the response:

| `state` | `responseData` | Meaning |
|---|---|---|
| `completed` | | The payment is paid, or authorized when the payment method authorizes payments. A pending capture leaves the payment `processing` until PayPal completes it. |
| `processing` | `awaiting_payer: true` | The payer has not finished at the bank yet. Send the same `PUT` again once they are back. |
| `cancelled` | `reason` | The payer did not approve the PayPal order, or has to repeat 3D Secure (`three_d_secure: retry`). The payment can be paid again. |
| `failed` | `reason` | PayPal declined the payment, or 3D Secure failed (`three_d_secure: declined`). |

After `cancelled` or `failed`, read the order (`GET /api/v2/shop/orders/{tokenValue}`) and create a new Payment
Request (step 2) for the last of its `payments`. When the payment itself failed (a declined 3D Secure, capture or
authorization), Sylius adds a new payment with a new id at the end of `payments`.

If the `PUT` never comes, e.g. the payer approves and closes the tab, the `CHECKOUT.ORDER.APPROVED` webhook of the
payment method captures or authorizes the approved PayPal order and the `PAYMENT.CAPTURE.*` webhooks settle a pending
capture, so make sure the webhooks are registered. Schedule `bin/console sylius-paypal:settle-payments` to settle
payments left `processing` or `authorized` when a webhook does not arrive.

## Validation

A PayPal Payment Request created through the shop API answers `422` unless:

- the action is `capture` or `authorize` matching the payment method, or `status`;
- the payment method is a PayPal payment method of the order's channel;
- the payload is exactly `{"payment_source": "<source>"}` with a payment source enabled for the payment method.

`PUT` checks the payload the same way only while the Payment Request is `new`.
