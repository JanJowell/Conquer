# Participant course maps: API and Flutter handoff

## What this change delivers

Laravel exposes the administrator's saved category course through the existing
category resource. Category metadata does not contact Google. Optional image
delivery is implemented separately and disabled by default; see
[image delivery](course-map-image-delivery.md). No keys or Flutter code were
changed. Existing uploaded images remain available. This addition needs
no new migration beyond the editor's existing `course_map` column migration.

The route is administrator-provided event information, not a navigation service
or a guarantee of course safety. Administrators must review the real course.

## Response contract

The field appears in:

- `GET /api/events`: `data[].categories[].course_map`.
- `GET /api/events/{event}`: `data.categories[].course_map`.
- `GET /api/my-registrations`: `data[].category.course_map` (authenticated).
- Other responses that already include a loaded `CategoryResource`, including
  an authenticated event's current registration categories.

Existing event visibility and registration authorization rules are unchanged.
Event category lists still include only open categories. The field is additive;
no participant write permission is introduced. The optional image endpoint has
separate authentication and request limits.

Example category fragment:

```json
{
  "course_map": {
    "version": 1,
    "path": [
      {"lat": 14.46, "lng": 120.96},
      {"lat": 14.47, "lng": 120.97}
    ],
    "start": {"lat": 14.46, "lng": 120.96},
    "finish": {"lat": 14.47, "lng": 120.97},
    "is_loop": false,
    "checkpoints": [
      {"lat": 14.465, "lng": 120.965, "label": "Water station", "number": 1}
    ]
  },
  "checkpoint_map_image_url": "https://example.test/storage/categories/maps/fallback.jpg"
}
```

- `course_map` is null for absent, malformed, or unsupported stored data. It never
  contains an API key, signing secret, or generated map URL.
- Coordinates are JSON numbers, normalized to six decimal places. Flutter should
  accept `num` and convert to `double`, not require the JSON value to be a double.
- `path` contains 2–1,000 points in saved order with at least two distinct points.
  Do not reorder, snap, simplify, or silently close the participant route.
- `start` and `finish` are the first and last normalized path points. `is_loop`
  means these match exactly, not merely that they are close together.
- `checkpoints` contains 0–50 entries in administrator order, numbered from 1.
  Labels contain up to 120 characters and must be displayed as plain text.
  Checkpoints are not necessarily path vertices or guaranteed to lie on the line.
- The derived fields are read-only API presentation; the stored/admin form schema
  remains `version`, `path`, and `checkpoints` only.
- `checkpoint_map_image_url` remains independently nullable and unchanged even if
  the saved route is cleared or invalid. Older clients can ignore `course_map`.

## Flutter implementation instructions

1. Add nullable `courseMap` parsing to the shared category model and any local
   category serialization. Treat absent fields, unsupported versions, invalid
   coordinates, or malformed arrays as no usable map, not a page-wide error.
2. Once a map renderer is integrated, show the selected category's saved polyline,
   Start/Finish, and checkpoint markers. For a loop use a combined Start/Finish
   marker so one does not hide the other. Show checkpoint labels and numbers in a
   readable list. Fit the viewport to the route and checkpoints after map layout.
3. Use stable category-specific marker IDs. Changing category must clear previous
   geometry; do not retain another category's course on loading or failure.
4. If there is no supported route, or the renderer cannot load, show the existing
   uploaded image when available. If neither is usable, show “Course map is not
   available yet.” Never pretend the route loaded successfully when it did not.
5. For the optional static-image renderer, use `course_map_image.url` with the
   bearer token and display its `legend`. Follow the dedicated
   [image contract](course-map-image-delivery.md), including stale-route recovery
   and fallback. Never construct a Google URL with the admin browser key in Flutter.
6. Test absent/null/unknown-version routes; image-only and route-only categories;
   loop/open courses; empty and 50 checkpoints; 1,000 points; labels containing
   markup; category switching; renderer/network errors; and registration details.
   Recheck that older API payloads still parse and old uploaded images still work.

No Flutter changes or APK rebuild are included in this backend task.

## Automatic image delivery

The optional endpoint uses Google Maps Static API to render the saved course on demand, not AI-generated
geography or a screenshot of the admin editor. It supports path overlays and
markers. Encode the path and check the final URL against its 16,384-character
limit; do not silently truncate the course. Built-in marker labels support only
one uppercase alphanumeric character, so they cannot directly represent all 50
checkpoint numbers. The implementation uses blue/purple single-character tokens
with an external numbered legend covering all 50 checkpoints. Flutter must display
that legend. [Static Maps request documentation](https://developers.google.com/maps/documentation/maps-static/start).

Keep URL signing on the backend. Google recommends a key plus digital signature;
the signing secret must never enter Flutter or an API response. Signatures do not
expire, so signing alone is not a spending limit or replay defense.
[Google signing guidance](https://developers.google.com/maps/documentation/maps-static/digital-signature).

Before enabling images, configure an appropriately restricted dedicated Static
Maps key and review the endpoint's request limits and usage budget. Do not
weaken/reuse the admin JavaScript key's restrictions. Platform
restrictions must match where requests originate.
[Google API security guidance](https://developers.google.com/maps/api-security-best-practices).

Do not implement permanent Google-image downloads into the existing uploaded-file
storage or promise offline imagery without establishing permission under the
applicable terms. Preserve all Google attribution and review the app's required
terms/privacy notices. Google's terms restrict content storage/caching except
where expressly permitted. [Google Maps Platform terms, sections 3.2.2–3.2.3](https://cloud.google.com/maps-platform/terms).

Acceptance before release: approved course remains accurate, checkpoint mapping
is unambiguous, attribution stays visible, long routes fail safely, image requests
are controlled, and the uploaded-image fallback still works. Run one configured
end-to-end device check only after deployment is explicitly approved.
