# Checkpoint History

Open **Checkpoint History** in the web dashboard sidebar. Admins can view
recorded visits across events; Event Managers can view only events currently
assigned to them. Participants cannot open this page.

The page is read-only and shows:

- Participant name and BIB as recorded, including leading zeroes.
- Current event and category names for context.
- Checkpoint order and label as recorded at the visit.
- Server timestamp, with the configured application timezone shown above the
  table, and elapsed time from the category's actual start.
- The recorded staff name, retained even if that staff account is later removed.

Search by recorded participant name or BIB, or filter by event and category.
Changing the event clears the category selection. Filter choices and search
persist through pagination. Visits are ordered by newest server timestamp,
then newest record ID, with 20 visits per page. Refresh keeps filters and returns
to the newest page; Clear removes filters. Refresh is manual, not automatic.

These records show checkpoint passages, not finishes, official results or a
participant's current location. A missing record alone does not establish a
missed checkpoint. **After scheduled end** is informational, not automatic
disqualification. Durations do not wrap after 24 hours.

The page provides no edit, delete, recording, finish, publication or certificate
actions. Standard dashboard access auditing remains in place. It does not expose
QR payloads, client request UUIDs, request fingerprints, map geometry, participant
emails or API credentials. Responses are private and not cacheable.

Saved history remains readable when checkpoint recording is switched off.
Opening the page does not enable recording or change which events may be scanned.
Existing authentication, verified administrative email and required two-factor
setup remain enforced. Suspended and banned accounts cannot access the page.

The page uses the existing checkpoint-history schema and introduces no new
migration. If that schema is unavailable in an environment, an explicit setup
notice is shown instead of a SQL error or a misleading zero-visits result.
