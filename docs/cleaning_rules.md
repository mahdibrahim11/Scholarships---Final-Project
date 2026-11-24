# Bulk import: cleaning and validation rules (Iteration 3)

Each uploaded row is cleaned, then validated. The cleaning functions are in `includes/import/cleaner.php`, the row and file checks in `includes/import/pipeline.php`, and 96 unit tests in `tests/cleaner_test.php` prove each rule.
Nothing reaches the `scholarships` table until an administrator reviews the report and presses **Commit**.

## What happens to a file
1. **Intake check** (`check_upload`): the extension must be `.csv` or `.xlsx`, the file must be under the size limit (5 MB by default, `app.max_upload_mb`), and the content must really look like that type (an `.xlsx` is a zip starting with `PK`; a `.csv` must be text). A renamed `.txt` or a fake `.xlsx` is refused with a plain message.
2. **Student**: the `LastName_FirstName` part of the file name picks the contributing student (extra suffixes such as `_v2` are ignored). If no student matches, the file still loads but the report says the student is unknown.
3. **Headers** are matched to the template columns ignoring case, spacing and punctuation, with a list of accepted aliases. Missing required columns stop the file with a clear message.
4. **Each row is cleaned and validated** (below) and gets a status. Rows go to a staging table, not to the real data.
5. **Report**: counts and one line of reasons per row. **Commit** (one transaction) imports Ready and Warning rows. **Discard** throws the file away. A committed batch can later be **rolled back** as a unit.

## Statuses
| Status | Meaning | Imported on commit? |
|---|---|---|
| Ready | Nothing wrong | Yes |
| Warning | Usable, but something was fixed, guessed or is missing; the reason is shown | Yes |
| Duplicate | The normalized URL already exists as an active scholarship, or appears earlier in this or another uploaded file | No |
| Error | Missing something required (name, provider, usable URL, or a recognizable state) | No |

## Cleaning rules
| Field | Rule |
|---|---|
| All text | Trim; turn non-breaking and zero-width spaces into normal spaces; collapse repeated whitespace |
| State | Full name, abbreviation, lowercase or `MN.` all become the 2-letter code; `National`/`Nationwide` become `US` (shown as National) |
| Scope | National if two or more states are listed or the value says National; otherwise state/local. If the sheet's own label disagrees with the states, the states win and a Warning says so |
| Yes/No columns | `yes/y/true/1` -> Yes, `no/n/false/0` -> No, blank or `unknown` -> Unknown; anything else -> Warning and Unknown |
| Money | `$1,000` -> 1000; `$500 - $2,000` -> min 500, max 2000; `Up to $5,000` -> max only; `Varies`/blank -> no amount (Warning) |
| Dates | ISO, US slash dates (month/day/year), written months and Excel serial numbers are read; 2-digit years mean 20xx; `Rolling`, `Ongoing`, `Year-round` set the rolling flag. Past deadlines get a Warning (hidden from public search) |
| Grades | `9-12`, `9th through 12th`, `6, 7, 8`, `middle school`, `high school`, `K-12` become grades 6 to 12; grades outside 6-12 are dropped with a Warning. School level (middle, high, both) is derived from the grades |
| GPA, number of awards | Whole numbers and 0 to 4.5 only; anything else is left blank with a Warning |
| URL | Must be http(s) with a real host. A missing scheme gets `https://` (Warning). Known aggregator sites (Fastweb, Bold.org and similar) get a Warning to use the provider's own page |
| Provider | Matched to an existing provider by a normalized name (case, "The", punctuation ignored); a new provider is created only on commit |
| Category and field lists | Split on commas and semicolons; values not in the seeded category/field lists are not imported (they stay visible in the staging report) |
| Assigned ranges | If the provider's first letter falls outside the student's assigned sponsor range, or the state is not the student's state, a Warning says so (a hint, not a block) |

## Duplicate detection
The normalized URL is: lowercase host, no leading `www.`, `http` and `https` treated as equal, no trailing slash, no `#fragment`, tracking parameters (`utm_*`, `fbclid`, `gclid` and similar) removed, remaining parameters sorted. `HTTPS://www.Example.org/Apply/?utm_source=x&id=7#top` becomes `example.org/Apply?id=7`.
Duplicates are found against the live table and across every file in the same upload. The database also enforces it: only **active** scholarships must have unique URLs, so archiving a scholarship frees its URL.

## Decisions to confirm with the instructor
See `docs/open_questions.md`. In particular, "20 scholarships per student" is **reported** (progress toward 20 on each report and on `admin/import/coverage.php`) but not enforced, because rejecting a file that has 19 good rows would lose real data.
