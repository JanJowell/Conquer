# Optional course-map image delivery

## Status

Implemented in Laravel and tested with simulated provider responses. Disabled by
default. No production credentials, configuration, deployments, Flutter screens,
or APKs were changed. This is not yet a live/device-verified map feature.

The existing uploaded `checkpoint_map_image_url` remains independent and unchanged.
No new migration is needed beyond the course editor's existing `course_map` column.

## API contract

Category responses include nullable `course_map_image`. When configured and the
saved route is usable, its shape is:

```json
{
  "url": "https://example.test/api/categories/12/course-map-image?revision=<sha256>",
  "requires_authentication": true,
  "legend": [
    {"lat": 14.46, "lng": 120.96, "kind": "start", "number": null, "label": "Start", "marker_label": "S", "marker_color": "green"},
    {"lat": 14.47, "lng": 120.97, "kind": "finish", "number": null, "label": "Finish", "marker_label": "F", "marker_color": "red"},
    {"lat": 14.465, "lng": 120.965, "kind": "checkpoint", "number": 1, "label": "Water station", "marker_label": "1", "marker_color": "blue"}
  ]
}
```

The field is null when disabled/unconfigured, no valid saved route exists, the
category is not persisted, or the final provider URL would be too long. Computing
metadata never fetches Google. No Google URL, key, or signing secret is exposed.

Fetch `url` using `GET` and `Authorization: Bearer <participant-token>`.
Successful responses are `image/png`, 1280 x 960 pixels (640 x 480 at scale 2),
with `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`.
The backend forwards validated image bytes without editing/cropping attribution,
saving them to storage, or redirecting clients to Google.

Access requires an event whose existing effective status is public, and either
an open category or the caller's non-rejected registration for that category.
Unknown/inaccessible records return 404, invalid authentication 401 (existing
ban/suspension rules also apply). Knowing a URL does not grant access. HEAD probes
never call Google and return 405 after access/revision checks.

The revision binds the image to the normalized route, checkpoint labels, and
renderer version. It is not an access token. Missing/mismatched revisions return
409 without fetching Google. Clients should refresh category metadata once, then
use the new URL and legend together; never repeatedly retry a stale URL.

Controlled generation failures return JSON, not an image:

```json
{
  "message": "The generated course map is unavailable.",
  "reason": "provider_unavailable",
  "fallback_image_url": "https://example.test/storage/categories/maps/fallback.jpg"
}
```

`fallback_image_url` can be null. Reasons include `not_configured` (503), `no_route`
(404), `route_changed` (409), `route_too_large` (422), `daily_limit` (429),
`budget_unavailable` (503), and provider/invalid-image failures (502). Middleware
errors such as 401/404/429 may use the existing generic API error format instead;
always retain the category's `checkpoint_map_image_url` for fallback.

## Rendering and legend

The saved path is encoded at standard polyline precision (five decimal places);
the database and route-data API keep their six-decimal normalized coordinates.
All vertices are used, without snapping, simplifying, or silently truncating.
Google chooses a viewport that includes the supplied path and markers.

Open courses use green S (Start) and red F (Finish). Exact loops use one green S
with the legend label “Start / Finish”. Checkpoint numbers remain 1–50 in the
legend. Image tokens are blue `1–9, A–Z` for checkpoints 1–35, then purple
`1–9, A–F` for 36–50. Match both token and color, not the token alone. Marker
labels do not contain administrator text; labels are shown as plain text in the
external legend. Nearby/coincident markers can overlap; the full legend retains
every checkpoint and its coordinates. Inspect real courses for readability.

Google limits built-in labels to one character and URLs to 16,384 characters.
Requests exceeding the final URL limit are refused, not shortened. Provider
warning headers also fail safely rather than presenting a potentially incomplete
map. [Static Maps request documentation](https://developers.google.com/maps/documentation/maps-static/start).

## Flutter handoff

1. Parse nullable `course_map_image`, preserving the old uploaded-image field.
2. Fetch the Laravel URL only when the participant opens that category's map.
   Send the bearer token only to the trusted backend, not to fallback/Google URLs.
3. On successful PNG display, show the matching legend including marker color,
   token, checkpoint number, and plain-text name. Keep attribution visible; do
   not crop the map to fill a differently shaped frame.
4. Handle non-200 responses/timeouts without a page crash. Fall back to the
   uploaded image; if absent/unavailable show “Course map is not available yet.”
   Refresh metadata once for a 409. Do not loop/retry provider errors automatically.
5. Do not persist Google image bytes for offline use. Avoid repeated loads from
   widget rebuilds. Clear image/legend state when the category or revision changes.
6. Test disabled metadata, no route, image-only and route-only categories, loops,
   50 checkpoints, expired tokens, 409/429/502/503, and category switching.

Flutter implementation and a real-device check are still required.

## Deployment configuration (not performed)

Leave `GOOGLE_MAPS_STATIC_ENABLED=false` until deployment and a controlled live
test are approved. When ready:

- Use a dedicated Maps Static API key in `GOOGLE_MAPS_STATIC_KEY`, restricted to
  that API and the backend's stable outbound IP(s). Confirm the host supports a
  stable egress address before configuring IP restrictions; do not weaken the
  admin HTTP-referrer key to make server calls work.
- Store the URL-safe Base64 signing secret only in `GOOGLE_MAPS_SIGNING_SECRET`.
  Keep both values out of source control, client builds, request logs, and chats.
- Use a shared database or Redis cache, including its lock support, across all
  backend instances. Outside testing, image delivery refuses other cache drivers.
- Set `GOOGLE_MAPS_STATIC_DAILY_LIMIT` deliberately (default 100 attempts per UTC
  day; zero blocks all requests). Then enable the feature and refresh config using
  the normal deployment process. Existing browser-editor settings are unaffected.

The endpoint limits requests to 6/minute per hashed bearer token and 30/minute per
IP. A shared, locked counter reserves each provider attempt before making it;
failed provider calls also count. Cache/lock failure prevents provider calls.
Requests use a fixed Google host, server-owned parameters, a 3-second connection
timeout, 10-second request timeout, no redirects, no retries, and a 4 MB response
limit. Client query parameters cannot override geometry, keys, provider, or size.
PNG type and expected dimensions are checked before delivery.

The application counter is a safety guard, not a Google billing cap. Clearing or
isolating its cache resets/splits it, and requests from other applications are not
counted. Review Google quotas, billing alerts, and actual usage before enabling.
Never enable it expecting the existing browser key alone to be sufficient.

Google recommends signing server-generated requests. Signatures do not expire;
this implementation keeps the signed request server-side behind authentication
and request controls. [Signing guidance](https://developers.google.com/maps/documentation/maps-static/digital-signature).
Google recommends restricted credentials and authenticated proxies for protected
server-side requests. [Security guidance](https://developers.google.com/maps/api-security-best-practices).

Review the applicable Maps terms/privacy notices before public use. Do not turn
this transient delivery into permanent image storage/offline downloads without
establishing permission. [Google Maps Platform terms](https://cloud.google.com/maps-platform/terms).

## Verification

```sh
php artisan test --compact tests/Feature/Api/CourseMapImageTest.php tests/Feature/Api/ParticipantCourseMapTest.php
```

Tests block real HTTP and use synthetic PNGs, fake errors, and test credentials.
They cover signing, polyline encoding, access, disabled configuration, stale
revisions, checkpoint mapping, response limits, request budgets, and fallbacks.
They do not verify live Google tiles, key restrictions, billing, or Flutter display.
