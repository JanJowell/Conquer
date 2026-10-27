# Category course-map editor (admin prototype)

The editor is available in Create/Edit Event category rows and the separate
Create/Edit Category pages. An authorized administrator or assigned event manager
can draw a course, adjust its vertices, and add named checkpoints. The first and
last route points supply Start and Finish labels automatically. **Finish at Start**
closes a loop exactly. Undo affects the current draft.

This phase saves an administrator-drawn route. It does not infer a safe race course,
snap to roads, calculate category distances, generate a downloadable map image, or
replace the participant application's uploaded checkpoint-map image. Review the
actual course before applying it. The participant API delivers saved route data
and now has an optional, disabled-by-default image endpoint. See
[image delivery](course-map-image-delivery.md). Flutter display remains separate.

## Configuration and release

1. Set `GOOGLE_MAPS_BROWSER_KEY` in the server environment. For Railway, use the
   service's Variables settings; never commit a real key in an example file.
2. Use a dedicated browser key with **Websites (HTTP referrers)** restrictions for
   the actual admin hostname and restrict its APIs to **Maps JavaScript API**.
   A development hostname needs its own allowed referrer or separate development
   key. Browser keys appear in the page by design, so restrictions are essential.
3. Apply migration `2026_10_21_000001_add_course_map_to_categories_table` as part of
   deployment (`php artisan migrate --force` in production). It adds a nullable
   JSON column; no existing image or category data is converted or deleted.
4. Refresh Laravel's configuration cache through the normal deployment process.
5. On the configured hostname, open one category editor, draw a small sample
   course, add a checkpoint, review it, select **Use This Route**, and save the
   event/category. Reopen it and verify the geometry and labels. Then check an
   existing uploaded image in the participant app.

If the key is missing, Open Map Editor is disabled and the existing uploaded-image
workflow remains available. Authentication and connection failures leave applied
route data intact. The new migration must be applied before saving category forms
with this release, even when the editor is not configured.

Only opening an editor loads Google Maps. The loader is shared across categories
in the current document; reopening the same editor reuses its map. Opening another
category creates another map instance and may count as another billable map load.
The editor itself uses no Places, Routes, or Maps Static API. Budget alerts are
notifications, not a spending cap. Monitor actual Google Cloud billing and usage.

## Save and recovery behavior

- Drawing and checkpoint edits remain a draft until **Use This Route** is selected.
- Applying requires at least two distinct points and confirmation that the course
  has been reviewed. Changes reset this confirmation.
- The parent event/category form saves the applied route to the database. A dirty
  draft blocks form submission so it cannot silently disappear.
- **Discard Draft / Close** restores the last applied route in the form.
- **Remove Saved Route** stages removal; saving the parent form confirms it. It
  does not remove the uploaded checkpoint-map image.
- Leaving the page warns about drafts or applied changes that have not been saved.
- Each dynamic category row has its own route, undo history, and checkpoints.
- Existing administrator/event-manager permissions apply to every server write.

## Stored data and validation

The category's `course_map` is nullable JSON:

```json
{
  "version": 1,
  "path": [
    { "lat": 14.46, "lng": 120.96 },
    { "lat": 14.47, "lng": 120.97 }
  ],
  "checkpoints": [
    { "lat": 14.465, "lng": 120.965, "label": "Water station" }
  ]
}
```

Admin forms submit JSON in `course_map`, or `categories[index][course_map]` for
embedded category rows. The server validates before normalizing coordinates to
six decimal places and trimming labels. Limits are 2–1,000 route points, 50
checkpoints, and 120 characters per checkpoint label. Coordinates must be valid
latitude/longitude values. Unknown fields, unsupported versions, malformed JSON,
and routes with fewer than two distinct normalized points are rejected.

An omitted field preserves an existing route. An explicit empty or null field
clears it. Start/Finish are derived from `path`; checkpoint numbering follows array
order. Labels are treated as text, never rendered as HTML.

The mobile `checkpoint_map_image_url` contract is unchanged. The shared category
response additionally exposes nullable `course_map`, with derived Start/Finish,
loop state, and checkpoint numbers. See [Participant course maps](participant-course-map.md).
No Google key is sent through that API, and the Flutter app is not changed by this
backend addition.

## Automated checks

```sh
php artisan test --compact tests/Feature/AdminCategoryCourseMapTest.php tests/Feature/AdminCategoryCheckpointMapTest.php
php artisan test --compact tests/Feature/Api/ParticipantCourseMapTest.php
node --test tests/Js/course-map-data.test.js tests/Js/course-map-browser.test.js
```

The browser test uses Node 22+ and an installed Chrome/Chromium/Edge executable.
Set `CHROME_PATH` if it is not in a detected location. It renders the actual shared
Blade editor and intercepts Google script requests with a test implementation; it
does not use a real API key, production records, or billable Google requests.
It checks lazy loading, review and submission guards, loop closure, checkpoint
text handling, undo/discard, dynamic category isolation, navigation, and load
failure recovery. It does not verify real Google tiles, billing, or referrer
restrictions; those still require the configured-hostname check above.

Google references:

- [Editable polylines](https://developers.google.com/maps/documentation/javascript/shapes)
- [Loading the Maps JavaScript API](https://developers.google.com/maps/documentation/javascript/load-maps-js-api)
- [Custom overlays](https://developers.google.com/maps/documentation/javascript/customoverlays)
