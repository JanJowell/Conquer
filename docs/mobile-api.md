# Conquer Mobile API

Base URL for local development:

```text
http://localhost:8000/api
```

For Android emulator, use your machine IP or `http://10.0.2.2:8000/api` when Laravel is running on the host.

## Auth

Send protected requests with:

```text
Authorization: Bearer <token>
Accept: application/json
```

### Public Endpoints

```text
GET  /api/health
GET  /api/config
POST /api/register
POST /api/login
POST /api/verify-email
POST /api/resend-verification-code
POST /api/forgot-password
POST /api/verify-reset-code
POST /api/reset-password
GET  /api/events
GET  /api/events/{event}
GET  /api/announcements
GET  /api/training-modules
GET  /api/training-modules/{module}
GET  /api/community-posts
```

### Protected Endpoints

```text
GET    /api/me
PATCH  /api/me
PATCH  /api/me/interests
POST   /api/me/avatar
PATCH  /api/me/password
POST   /api/logout
GET    /api/my-registrations
GET    /api/my-results
GET    /api/achievements
GET    /api/notifications
POST   /api/events/{event}/register/{category}
GET    /api/registrations/{registration}/payments
POST   /api/registrations/{registration}/paymongo-checkout
POST   /api/registrations/{registration}/payment-proof
GET    /api/community-posts/{post}
POST   /api/community-posts
DELETE /api/community-posts/{post}
```

## App Config

```text
GET /api/config
```

Important mobile option lists:

```json
{
  "interests": ["Cycling", "Duathlon", "Hiking", "Marathon", "Trail Run", "Triathlon"],
  "event_interest_types": ["Cycling", "Duathlon", "Hiking", "Marathon", "Trail Run", "Triathlon"],
  "training_focus_types": ["Cycling", "Duathlon", "Hiking", "Marathon", "Trail Run", "Triathlon"],
  "shirt_sizes": ["XS", "S", "M", "L", "XL", "2XL", "3XL"]
}
```

Use `interests` for profile chips. Use `event_interest_types` for event filters/type labels and `training_focus_types` for training module recommendations. The event and training focus arrays intentionally contain the same values.

When building mobile UI:

- Profile setup/edit chips should come from `interests`.
- Event type filters and event labels should come from `event_interest_types`.
- Training recommendation filters should come from `training_focus_types`.

## Login Example

Request:

```json
{
  "email": "runner@example.com",
  "password": "password123"
}
```

The login endpoint also accepts `username` or `identifier` instead of `email`.

If the account has not verified its email yet, login returns:

```json
{
  "message": "Please verify your email before logging in.",
  "email_verification_required": true
}
```

Response:

```json
{
  "message": "Login successful.",
  "token": "plain-token-returned-once",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Runner Name",
    "email": "runner@example.com",
    "role": "runner",
    "avatar_url": null
  }
}
```

## Register Example

The mobile app can send either `name` or `first_name` plus `last_name`. A real `email` is required because the user must verify it before logging in. `username` is optional.

```json
{
  "first_name": "Keith",
  "last_name": "Garcia",
  "username": "keithgarcia",
  "email": "keithgarcia@gmail.com",
  "gender": "Male",
  "birthdate": "2000-01-01",
  "password": "password123",
  "password_confirmation": "password123",
  "interests": ["Cycling", "Marathon"]
}
```

The API also accepts `confirm_password` or `re_enter_password` as the confirmation field.

Successful registration sends a 6-digit email verification code and returns:

```json
{
  "message": "Registration successful. Please verify your email before logging in.",
  "email_verification_required": true
}
```

## Verify Email

```text
POST /api/verify-email
```

```json
{
  "email": "keithgarcia@gmail.com",
  "code": "123456"
}
```

The code expires after 15 minutes. To send a new code:

```text
POST /api/resend-verification-code
```

```json
{
  "email": "keithgarcia@gmail.com"
}
```

## Interests

```text
PATCH /api/me/interests
```

```json
{
  "interests": ["Cycling", "Duathlon", "Marathon"]
}
```

The API trims interest strings, removes duplicates case-insensitively, and maps known values to canonical spelling. For example, `marathon`, `MARATHON`, and ` marathon ` are saved and returned as `Marathon`.

Unknown interest strings are still accepted and preserved for backward compatibility, but the mobile profile picker should only offer the values from `/api/config.interests`: `Cycling`, `Duathlon`, `Hiking`, `Marathon`, `Trail Run`, and `Triathlon`.

## Find Events

```text
GET /api/events?search=run
GET /api/events?interest=Marathon
GET /api/events?status=upcoming
GET /api/events?recommended=1
```

Send `Authorization: Bearer <token>` with `recommended=1` to prioritize events whose `interest_type` matches the user's saved interests. Without a valid token or matching interests, it falls back to the normal event feed.

Each event response includes `participants_count`, `interest_type`, `banner_url`, and loaded `categories`. The event list response includes `meta.recommended` and `meta.matched_interests`.

Use `/api/config.event_interest_types` for event filter options.

## Training Modules

```text
GET /api/training-modules
GET /api/training-modules?interest=Marathon
GET /api/training-modules?interests=Marathon,Trail%20Run
GET /api/training-modules?recommended=1
```

Send `Authorization: Bearer <token>` with `recommended=1`. When the token matches an active mobile user, the API uses the user's saved interests, returns matching focused modules first, and still includes general modules where `interest_type` is empty. Without a valid token or matching interests, it falls back to the normal published module feed.

Each training module includes `interest_type`. `null` means general training.

Use `/api/config.training_focus_types` for training focus filters.

## Event Registration

```text
POST /api/events/{event}/register/{category}
```

Request:

```json
{
  "shirt_size": "M",
  "medical_conditions": "None",
  "first_aid_kit_confirmed": true,
  "waiver_accepted": true,
  "waiver_name": "Keith Garcia"
}
```

The API checks that the event is upcoming, the deadline has not passed, the category is open, slots are still available, the runner confirmed their personal first aid kit, and the runner accepted the event waiver.

For categories with `distance_km` of `50` or higher, use `multipart/form-data` and include:

```text
medical_certificate: required, jpg/jpeg/png/webp/pdf, max 5MB
```

Category objects include `requires_medical_certificate` so the mobile app can show the upload field only when needed. Registration responses include `medical_certificate_url`, `medical_certificate_submitted_at`, `first_aid_kit_confirmed`, `waiver_accepted`, `waiver_accepted_at`, and race-kit waiver timestamps.

For paid categories, the registration response includes payment fields:

```json
{
  "payment_required": true,
  "payment_status": "unpaid",
  "payment_amount_cents": 50000,
  "payment_amount": "500.00",
  "payment_currency": "PHP",
  "latest_payment": null
}
```

Category objects include direct-registration payment details when the category has a fee:

```json
{
  "price_cents": 50000,
  "price_amount": "500.00",
  "price_currency": "PHP",
  "is_free": false,
  "payment_instructions": {
    "provider": "GCash",
    "account_name": "Conquer Events",
    "account_number": "09170000000",
    "instructions": "Upload your receipt screenshot after paying."
  }
}
```

`payment_instructions` is `null` for free categories. For online direct registration, use these details for BPI, Maya, or GCash transfer instructions, then let the runner submit proof. Cash payments for in-store or onsite registration are handled from the admin payments page.

## Payments

`GET /api/config` includes payment gateway availability:

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

Manual payment status values:

```text
unpaid     Runner has not submitted proof yet.
submitted  Runner submitted proof and is waiting for admin review.
pending    Admin marked payment pending review.
paid       Admin approved payment.
failed     Admin rejected proof; runner can submit again while registration is pending.
waived     Admin waived payment; no payment is required.
expired    Payment deadline passed and registration was rejected.
refunded   Payment was refunded and registration was rejected.
cancelled  Payment was cancelled and registration was rejected.
```

### Create PayMongo Checkout

Use this for online gateway payments:

```text
POST /api/registrations/{registration}/paymongo-checkout
```

The registration must belong to the signed-in user, require payment, still be `pending`, and have a payment status of `unpaid`, `pending`, `submitted`, or `failed`.

Example response:

```json
{
  "message": "PayMongo checkout session created.",
  "checkout_url": "https://checkout.paymongo.com/...",
  "data": {
    "id": 12,
    "payment_status": "pending",
    "latest_payment": {
      "provider": "paymongo",
      "provider_reference": "cs_...",
      "status": "pending",
      "checkout_url": "https://checkout.paymongo.com/..."
    }
  }
}
```

Open `checkout_url` in the mobile app. The app should wait for the backend/webhook-updated registration status instead of marking payment as paid locally.

### Submit Payment Proof

Use `multipart/form-data`:

```text
POST /api/registrations/{registration}/payment-proof
provider: optional, max 50 chars
provider_reference: required without proof_image, max 255 chars
proof_image: required without provider_reference, jpg/jpeg/png/webp, max 5MB
notes: optional, max 1000 chars
```

Successful response:

```json
{
  "message": "Payment proof submitted for admin review.",
  "data": {
    "id": 12,
    "payment_status": "submitted",
    "latest_payment": {
      "provider": "GCash",
      "provider_reference": "ABC123",
      "status": "submitted",
      "proof_url": "http://localhost:8000/storage/payment-proofs/example.jpg",
      "submitted_at": "2026-05-30T10:00:00.000000Z"
    }
  }
}
```

Proof upload is only accepted when:

- the registration belongs to the signed-in user
- the registration is still `pending`
- payment is required
- payment status is `unpaid`, `pending`, `submitted`, or `failed`

### Payment History

```text
GET /api/registrations/{registration}/payments
```

Returns the registration summary and all payment attempts:

```json
{
  "registration": {
    "id": 12,
    "status": "pending",
    "payment_status": "submitted"
  },
  "data": [
    {
      "id": 5,
      "provider": "GCash",
      "provider_reference": "ABC123",
      "status": "submitted",
      "amount": "500.00",
      "currency": "PHP",
      "proof_url": "http://localhost:8000/storage/payment-proofs/example.jpg",
      "notes": "Paid today",
      "submitted_at": "2026-05-30T10:00:00.000000Z",
      "paid_at": null,
      "created_at": "2026-05-30T10:00:00.000000Z"
    }
  ]
}
```

## Change Password

```text
PATCH /api/me/password
```

```json
{
  "current_password": "old-password",
  "new_password": "new-password123",
  "confirm_password": "new-password123"
}
```

## Avatar Upload

Use `multipart/form-data`:

```text
POST /api/me/avatar
avatar: jpg, jpeg, png, or webp, max 10MB
```

## Community Posts

To open one exact post, such as from a notification tap:

```text
GET /api/community-posts/{post}
```

The response uses the same post payload as the feed and includes `liked_by_me`, `likes_count`, comments, media URLs, event, and author data.

Text-only request:

```json
{
  "title": "New thread title",
  "content": "What's on your mind?",
  "event_id": 1
}
```

For image or video posts, use `multipart/form-data`:

```text
title: optional
content: required
event_id: optional
image: jpg, jpeg, png, or webp, max 4MB
video: mp4, mov, or webm, max 20MB
```

## Achievements

```text
GET /api/achievements
```

Returns all active e-badge templates in `data`, with `locked` or `unlocked` status for the signed-in runner.

```json
{
  "data": [
    {
      "id": 1,
      "title": "Top 3 Overall",
      "description": "Awarded to overall podium finishers.",
      "criteria": "Top 3 overall",
      "auto_issue_rule": "top_3_overall",
      "auto_issue_rule_label": "Top 3 overall",
      "image_url": "http://localhost:8000/storage/e-badges/top-3.png",
      "event": {
        "id": 2,
        "title": "City Fun Run"
      },
      "category": null,
      "status": "locked",
      "locked": true,
      "earned_count": 0,
      "issued_badge_id": null,
      "issued_at": null,
      "notes": null
    }
  ],
  "issued_badges": []
}
```

`issued_badges` is kept as a separate earned-badges list sorted by latest issued date. It includes the badge image, event, and category when a badge is event/category-specific.

## Notifications

```text
GET /api/notifications
```

Returns active notifications targeted to `all`, `participants`, `admins`, or directly to the signed-in user.

Community interaction notifications include deep-link metadata:

```json
{
  "id": 10,
  "title": "New comment on your post",
  "message": "Alex commented on your community post.",
  "type": "community",
  "data": {
    "action": "comment",
    "community_post_id": 25,
    "actor_id": 7,
    "actor_name": "Alex",
    "screen": "community_post"
  },
  "is_read": false
}
```

Recommended mobile tap flow:

1. If `type` is `community` and `data.screen` is `community_post`, call `GET /api/community-posts/{data.community_post_id}`.
2. Open the community post detail view with that payload.
3. Call `POST /api/notifications/{id}/read` after opening the notification.
