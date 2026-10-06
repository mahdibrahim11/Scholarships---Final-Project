# Scholarship Field Inventory

This document lists the scholarship fields our application must be able to import and store.

| Field | What it Means | Simple Rule |
|---|---|---|
| Scholarship name | Name of the scholarship | Required |
| Scholarship provider/organization | Organization offering the scholarship | Store with provider information |
| Provider type | Type of organization | Example: nonprofit, college, government |
| Official scholarship URL | Official scholarship webpage | Required and used to detect duplicates |
| Discovery source | Where the scholarship was originally found | Optional text |
| State/region | State where the scholarship applies | Use 2-letter state code or National |
| National vs. state/local | Whether the scholarship is national or local | National if open to 2 or more states |
| Eligible grade levels | Grades that can apply | Grades 6–12 |
| Middle school / high school / both | Type of students eligible | Can be based on grade levels |
| Scholarship category | Type of scholarship | Example: merit, STEM, arts, leadership |
| Field/area of interest | Area of study or interest | Can have more than one |
| Award amount | Amount of money awarded | Store as a number |
| Number of awards | Number of scholarships available | Whole number if provided |
| Renewable | Whether the award can be received again | Yes / No / Unknown |
| Maximum potential value | Maximum total amount possible | Store as a number if provided |
| Application open date | Date applications open | Date if available |
| Application deadline | Last day to apply | Date or rolling deadline |
| Residency requirement | Where the applicant must live | Text |
| Minimum GPA | Lowest GPA allowed | Decimal number if required |
| Financial need required | Whether financial need is required | Yes / No / Unknown |
| Essay required | Whether an essay is required | Yes / No / Unknown |
| Recommendation required | Whether recommendation letters are required | Yes / No / Unknown |
| Transcript/test score required | Whether transcript or scores are required | Yes / No / Unknown |
| Community service requirement | Whether community service is required | Yes / No / Unknown |
| Leadership requirement | Whether leadership experience is required | Yes / No / Unknown |
| Other major eligibility requirements | Other important requirements | Text |
| Application method | How the student applies | Online, email, mail, or other |
| Verification date | Last date the scholarship information was checked | Store as a date |
| Notes | Extra scholarship information | Text |


## Basic Import Rules

- Remove extra spaces from imported data.
- Convert state names to 2-letter state codes.
- Convert Yes/No fields into a consistent format.
- Convert money values like "$1,000" into numbers.
- Convert grade ranges like "9-12" into individual grade levels.
- Check for duplicate scholarships using the official URL.
- Make sure the official URL is valid.
- Mark each imported row as Ready, Warning, Duplicate, or Error.
