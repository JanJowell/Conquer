# PayMongo Setup

This project supports PayMongo hosted checkout while keeping manual payment proof as a fallback.

## Environment Variables

Set these values in the deployed Laravel `.env`:

```env
PAYMONGO_SECRET_KEY=sk_test_xxx
PAYMONGO_PUBLIC_KEY=pk_test_xxx
PAYMONGO_WEBHOOK_SECRET=whsec_xxx
PAYMONGO_BASE_URL=https://api.paymongo.com
PAYMONGO_SUCCESS_URL="${APP_URL}/payments/success"
PAYMONGO_CANCEL_URL="${APP_URL}/payments/cancelled"
PAYMONGO_PAYMENT_METHODS=gcash,paymaya,card
```

Use PayMongo test keys for sandbox testing and live keys only after a successful end-to-end test.

## Dashboard Setup

In the PayMongo dashboard:

1. Create or open the webhook for the deployed backend.
2. Set the webhook URL to:

```text
https://your-domain.com/api/paymongo/webhook
```

3. Enable checkout/payment events, especially:

```text
checkout_session.payment.paid
checkout_session.payment.failed
checkout_session.expired
```

4. Copy the webhook signing secret into `PAYMONGO_WEBHOOK_SECRET`.

## Laravel Deployment Steps

After changing `.env` on the server, clear cached configuration:

```bash
php artisan optimize:clear
```

Confirm the API routes exist:

```bash
php artisan route:list --path=api/paymongo
php artisan route:list --path=api/registrations
```

Confirm the public config endpoint reports the expected gateway state:

```text
GET /api/config
```

The response includes:

```json
{
  "payments": {
    "manual_proof_enabled": true,
    "paymongo_enabled": true,
    "paymongo_public_key_configured": true,
    "paymongo_webhook_configured": true,
    "paymongo_payment_methods": ["gcash", "paymaya", "card"]
  }
}
```

## Mobile Flow

For a paid registration, the mobile app should call:

```text
POST /api/registrations/{registration}/paymongo-checkout
```

The API returns:

```json
{
  "message": "PayMongo checkout session created.",
  "checkout_url": "https://checkout.paymongo.com/...",
  "data": {
    "payment_status": "pending",
    "latest_payment": {
      "provider": "paymongo",
      "provider_reference": "cs_...",
      "checkout_url": "https://checkout.paymongo.com/..."
    }
  }
}
```

Open `checkout_url` in the mobile app. Do not mark the registration as paid when the browser returns to the app. The backend marks payment as paid only after PayMongo sends the webhook.

## Webhook Behavior

`POST /api/paymongo/webhook` requires `PAYMONGO_WEBHOOK_SECRET`, verifies the
`Paymongo-Signature` header, and rejects signatures older than five minutes. If
the webhook secret is missing, the endpoint fails closed with HTTP 401.

When PayMongo sends `checkout_session.payment.paid`, the backend:

- marks the local `payments` record as `paid`
- marks the registration `payment_status` as `paid`
- approves a pending registration
- assigns a bib number if needed
- stores webhook details in the payment payload

When PayMongo sends `checkout_session.payment.failed` or `checkout_session.expired`, the backend marks the payment as `failed` unless it was already paid.

## Admin Review

Admins can inspect PayMongo payments from:

```text
Admin > Payments
```

The page shows the provider, reference, source, webhook event, checkout link, and history. Use the provider filter to show only `paymongo` records.

## Test Checklist

Before going live:

1. Use PayMongo test keys.
2. Run `php artisan optimize:clear`.
3. Register the webhook URL in PayMongo.
4. Create a paid event category with complete payment details.
5. Register from the mobile app.
6. Create a PayMongo checkout session.
7. Complete a sandbox payment.
8. Confirm admin Payments shows provider `Paymongo`.
9. Confirm the registration becomes `approved` after webhook payment.
10. Run the backend test suite:

```bash
php artisan test
```
