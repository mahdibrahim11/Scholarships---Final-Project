# Field inventory: Data Science template -> database -> cleaning rule

All 29 columns of the Data Science "Recommended Data Fields", in template order.
Column key = what the code calls it (`template_columns()` in `includes/import/pipeline.php`).

| # | Template column | Key | Stored in | Required? | Cleaning / validation |
|---|---|---|---|---|---|
| 1 | Scholarship name | name | scholarships.name | **Yes** (error) | trim, NBSP -> space |
| 2 | Scholarship provider/organization | provider | providers.name (+ name_key) | **Yes** (error) | trim; matched to existing provider by normalized key (ignores leading "The", punctuation, case) |
| 3 | Provider type | provider_type | providers.provider_type_id | No | matched to `provider_types` by name |
| 4 | Official scholarship URL | url | scholarships.official_url, url_key | **Yes** (error) | must be http(s) with a dotted host; scheme-less gets `https://` + warning; aggregator host = warning |
| 5 | Discovery source | discovery_source | scholarships.discovery_source | No | trim |
| 6 | State/region | state | scholarships.state_code | **Yes** (error) | full name/abbreviation -> 2-letter code; lists ("MN, WI") allowed; "National" -> `US` |
| 7 | National vs. state/local | scope | scholarships.scope | No (derived) | derived from the states (2+ = national); a conflicting value gives a warning and the derived value wins |
| 8 | Eligible grade levels | grades | scholarship_grades | No (warning if blank) | "9-12", "9th through 12th", "middle school", lists; clipped to 6-12; **no grade in 6-12 = error** |
| 9 | Middle school / high school / both | school_level | scholarships.school_level | No (derived) | computed from grades, not trusted from the sheet |
| 10 | Scholarship category | category | scholarship_categories | No | split on `, ; / |`; unknown names = warning and skipped |
| 11 | Field/area of interest | field | scholarship_fields | No | same as category |
| 12 | Award amount | award | award_min, award_max | No (warning if blank) | `$1,000`, `$500-$2,000`, `Up to $5,000`, `5k`; "Varies" = blank + warning |
| 13 | Number of awards | num_awards | num_awards | No | whole number |
| 14 | Renewable | renewable | renewable | No | Yes/No/Unknown |
| 15 | Maximum potential value | max_value | max_potential_value | No | money parse; warning if lower than the award |
| 16 | Application open date | open_date | open_date | No | many date formats, Excel serials |
| 17 | Application deadline | deadline | deadline, is_rolling | No (warning if blank) | "Rolling"/"Ongoing" -> `is_rolling`; past date = warning |
| 18 | Residency requirement | residency | residency_requirement | No | trim; if blank and multi-state, filled with the state list |
| 19 | Minimum GPA | min_gpa | min_gpa | No | 0 to 4.5 |
| 20 | Financial need required | need | need_required | No | Yes/No/Unknown |
| 21 | Essay required | essay | essay_required | No | Yes/No/Unknown |
| 22 | Recommendation required | recommendation | recommendation_required | No | Yes/No/Unknown |
| 23 | Transcript/test score required | transcript | transcript_required | No | Yes/No/Unknown |
| 24 | Community service requirement | community_service | community_service_required | No | Yes/No/Unknown |
| 25 | Leadership requirement | leadership | leadership_required | No | Yes/No/Unknown |
| 26 | Other major eligibility requirements | other_requirements | other_requirements | No | trim |
| 27 | Application method | application_method | application_method | No | trim |
| 28 | Verification date | verification_date | verification_date | No (warning if blank) | date parse; future date = warning |
| 29 | Notes | notes | notes | No | trim |

Added by the system (not in the template): national theme, contributing student, import batch, status, created/updated by/at.

**Row status rules:** *Error* (cannot import) if name, provider, URL, or state is missing/invalid, or no grade is 6-12. *Duplicate* if the normalized URL is already in the file, an earlier file, or the database. *Warning* if anything else is off. Otherwise *Ready*.
