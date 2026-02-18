# Development Rules (Divi Modifiability Requirement)

These rules apply to all future changes in this website project.

## Non-negotiable requirement
- Customer-facing content and layout MUST remain editable in Divi Visual Builder.
- Do **not** hardcode homepage sections/content in PHP templates when that content can be authored in Divi modules.

## Implementation guidance
1. `front-page.php` must render page content via `the_content()` so imported Divi JSON layouts stay editable.
2. Child theme CSS should be class-based helpers (example: `dra-hero`, `dra-btn--gold`) that can be attached in Divi module settings.
3. If fallback markup is needed, keep it minimal and instructional; never replace Divi-managed page content.
4. Prefer updating Divi templates/layout JSON and module classes over adding fixed template markup.

## Review checklist before commit
- Can a non-developer update text/images/buttons directly in Divi Builder?
- Can the imported Divi JSON layout still be used without code edits?
- Did we avoid introducing hardcoded business content in PHP templates?

If any answer is "No", revise the change before merging.
