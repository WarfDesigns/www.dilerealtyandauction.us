# Diler Realty & Auction Child Theme

## Divi-first editing (fully modifiable)
This child theme is set up so your **Home page content is 100% editable in Divi Builder**.
The `front-page.php` template outputs `the_content()`, which means your Divi layout (including imported JSON templates) controls the page.

## Local (v9.2.9+6887) workflow
1. Open your Local site and click **Start Site**.
2. Open **WP Admin** from Local and login.
3. Go to **Appearance → Themes** and activate **Diler Realty & Auction Child**.
4. Go to **Settings → Reading** and set a static homepage.
5. Edit that Home page with **Enable Visual Builder**.
6. Import your Divi JSON template:
   - In Divi Builder, click portability arrows (↕)
   - Go to **Import** tab
   - Upload your exported `.json`
7. Update hero section text/buttons to:
   - Button 1: `View Listings`
   - Button 2: `View Auctions`

## Styling notes
- The child theme provides a Red / Navy / White palette.
- You can assign custom classes in Divi modules and reuse these styles:
  - `dra-hero`
  - `dra-btn dra-btn--gold`
  - `dra-btn dra-btn--ghost`
  - `dra-section-title`
  - `dra-card`

## Fallback behavior
If no homepage content exists yet, a simple hero with a photo and the two required buttons is shown automatically.

## Project guardrail
- All future work must preserve Divi modifiability for customer edits.
- See `DEVELOPMENT_RULES.md` for the required implementation rules and review checklist.
