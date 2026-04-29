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
POST /api/forgot-password
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
POST   /api/community-posts
DELETE /api/community-posts/{post}
```

## Login Example

Request:

```json
{
  "email": "runner@example.com",
  "password": "password123"
}
```

The login endpoint also accepts `username` or `identifier` instead of `email`.

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

The mobile app can send either `name` or `first_name` plus `last_name`. It can send `email`, `username`, or both.

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
  "interests": ["Running", "Cycling"]
}
```

The API also accepts `confirm_password` or `re_enter_password` as the confirmation field.

## Interests

```text
PATCH /api/me/interests
```

```json
{
  "interests": ["Running", "Cycling", "Duathlon"]
}
```

## Find Events

```text
GET /api/events?search=run
GET /api/events?interest=Running
GET /api/events?status=upcoming
```

Each event response includes `participants_count`, `interest_type`, `banner_url`, and loaded `categories`.

## Event Registration

```text
POST /api/events/{event}/register/{category}
```

Request:

```json
{
  "shirt_size": "M",
  "medical_conditions": "None"
}
```

The API checks that the event is upcoming, the deadline has not passed, the category is open, and slots are still available.

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
avatar: jpg, jpeg, png, or webp, max 2MB
```

## Community Posts

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

Returns the badge list with `locked` or `unlocked` status for First Event, Explorer, Consistent Athlete, Early Bird, Social Athlete, and Champion.

## Notifications

```text
GET /api/notifications
```

Returns active notifications targeted to `all`, `participants`, or `admins` depending on the signed-in user.
