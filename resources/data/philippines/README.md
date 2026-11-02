# Philippine venue address reference

`locations.json` is generated from the Philippine Statistics Authority (PSA),
**Philippine Standard Geographic Code as of 30 September 2026**:

- Publication page: https://psa.gov.ph/classification/psgc/Philippine
- Source publication: https://psa.gov.ph/system/files/scd/PSGC-3Q-2026-Publication-Datafile.xlsx
- Terms and attribution: https://psa.gov.ph/terms-of-use (CC BY 4.0 unless otherwise stated).

The generated file records the original filename, SHA-256, reference date,
source, license, and transformations. The user downloaded the official workbook
because the site's automated download was blocked. The original XLSX is retained
under Git-ignored `storage/app/private/venue-reference/`; it is not required by
the running application and should not be uploaded with private storage files.

The source has 82 provinces, 149 cities, 1,493 municipalities and 42,010
barangays. The address picker has 102 province/area options: it does **not** claim
there are 102 provinces. Metro Manila, independent cities, and the Special
Geographic Area are represented as non-province address groups. Manila's
sub-municipal districts are rolled up to City of Manila for barangay selection.
PSA display-only rows explicitly marked `(Not a Province)` are not imported as
provinces. Other unknown geographic levels or orphan barangays fail the import.
Names are retained except for the documented descriptive area labels.

Barangays are stored in checksum-verified, per-city files. The small main index
contains province/area and city metadata, counts, and chunk checksums. The server
loads only one city's barangays rather than decoding the whole national list
for each request. Deploy the complete referenced `barangays-*` directory along
with `locations.json`; missing/damaged chunks fail safely instead of allowing
unverified address choices.

The browser requests only the selected level from an authenticated, same-origin
Laravel endpoint. No Google Places, geocoding, tiles, third-party autocomplete,
or external location API is used. The full nationwide list is not embedded in
every event form. Saved venues and their canonical address snapshots live in
the application's `venues` table; existing event venue strings remain readable.

To refresh, review the next official publication, download it once, and run:

```powershell
php -d memory_limit=768M artisan venues:import-locations path/to/official-publication.xlsx --reference-date=YYYY-MM-DD
```

An existing reference is not overwritten unless `--force` is explicitly supplied.
Review the resulting diff and update the pinned count/version tests. Importing
changes only the generated reference file, not event/venue/database records.
Old saved venue snapshots remain unchanged when a reference is updated.

The synthetic JSON under `tests/Fixtures/` is for tests only; it must never
replace the real nationwide reference or be used to seed production venues.
