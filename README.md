# MM SEO

A lean, full-featured WordPress SEO plugin by [Martini Multimedia](https://www.martini-multimedia.net): titles, meta, schema, sitemaps, redirects, a 404 monitor and more, without ads, upsells or tracking.

MM SEO is standalone. It is not a fork of another SEO plugin, and it ships its own analysis and scoring engine (the "MM Score"). Every feature is a module, so you enable only what you need.

![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![WordPress 6.6+](https://img.shields.io/badge/WordPress-6.6%2B-21759b)
![License GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

## Features

| Area | What you get |
| --- | --- |
| **Titles & meta** | Title/description templates per post type and taxonomy using `%%title%%`, `%%sitename%%`, `%%sep%%`, `%%excerpt%%`, `%%date%%`, `%%author%%`, `%%term_title%%`, `%%page%%` and more |
| **Social** | Open Graph and Twitter Card tags, per-post overrides, global fallback image |
| **Schema (JSON-LD)** | Article, WebSite, Organization/Person, BreadcrumbList, FAQPage, Event, Restaurant, plus travel types: TouristDestination, LodgingBusiness, TouristTrip |
| **XML sitemaps** | Index at `/sitemap_index.xml`, per-type sub-sitemaps, image entries, `lastmod` dates, XSL stylesheet |
| **Content analysis** | Live 0–100 score in the Gutenberg sidebar and the classic/Divi meta box (see [SEO checks](#seo-checks)) |
| **Taxonomy SEO** | Title, description, canonical and robots for every term |
| **Redirects** | 301 / 302 / 307 with regex support |
| **404 monitor** | Logs misses with referrer and hit count; one-click redirect creation |
| **IndexNow** | Pings Bing and Yandex on publish/update |
| **Breadcrumbs** | `[mmseo_breadcrumbs]` shortcode with BreadcrumbList schema |
| **robots.txt** | Virtual robots.txt editor (turns itself off if a physical file exists) |
| **Verification** | Google Search Console, Bing, Yandex, Pinterest meta tags |
| **RSS** | Prepend/append custom HTML to feed items |
| **WP-CLI** | `wp mmseo status`, `wp mmseo analyze`, `wp mmseo flush-sitemap` |

### SEO checks

The MM Score runs entirely on your server; no content is sent to external services. It checks:

- the focus keyword in the title, slug, meta description, first paragraph and subheadings
- keyword density
- title and meta description length
- content length
- internal and external links
- image alt attributes
- paragraph and sentence length

### Compatibility

- **Other SEO plugins:** if Yoast SEO, Rank Math, All in One SEO or SEOPress is active, MM SEO pauses its frontend output so you don't get duplicate tags. You can override this with **MM SEO → Advanced → Force output**.
- **Divi:** detects Divi's ePanel SEO and suppresses its duplicate output with a compatibility shim.
- **Multisite:** network activation seeds defaults on every site.
- **Translations:** includes Italian (`it_IT`) and a `.pot` template.

## Requirements

- WordPress 6.6 or later
- PHP 8.1 or later

## Installation

```bash
cd wp-content/plugins
git clone https://github.com/inalto/mm-seo.git
```

Then:

1. Activate **MM SEO** under **Plugins**.
2. Enable the modules you want in **MM SEO → Dashboard**.
3. Set up title/description templates in **MM SEO → Titles & Meta**.
4. Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules, so `/sitemap_index.xml` resolves.

## Migrating from another SEO plugin

> [!NOTE]
> Version 1.0.0 has **no importer**. MM SEO does not read per-post data saved by other SEO plugins. Posts without their own overrides use the global templates.

1. Activate MM SEO while your old plugin is still active. MM SEO detects the conflict and stays paused.
2. Configure your modules, templates, site identity and social profiles.
3. Re-enter per-post SEO titles, descriptions and focus keywords where you need them.
4. Deactivate the old plugin. MM SEO output turns on automatically.
5. Flush permalinks (**Settings → Permalinks → Save Changes**).
6. View the source of any page and check that there is exactly one `<title>`, one meta description, one canonical and one JSON-LD block.
7. Check that `/sitemap_index.xml` loads. Existing Search Console submissions using that URL keep working.
8. On Divi sites, set **Divi → Theme Options → SEO → Enable Divi SEO** to off.

## WP-CLI

```bash
wp mmseo status                  # SEO score distribution across posts
wp mmseo analyze --post=123      # score a single post
wp mmseo analyze --all           # score all posts
wp mmseo flush-sitemap           # clear the sitemap cache
```

## Privacy

MM SEO makes no outbound requests other than the IndexNow pings, and only if you enable that module. It has no telemetry, no ads and no upsells.

## Project structure

```
mm-seo.php            Bootstrap, autoloader (MMSEO\ → includes/), activation hooks
uninstall.php         Cleanup on uninstall
includes/
  Admin/              Settings page, meta box, editor sidebar, term SEO, columns, health check
  Analysis/           MM Score engine, REST controller, Checks/
  Frontend/           Title, meta tags, social, schema, breadcrumbs
  Modules/            Sitemaps, Redirects (+404 monitor), IndexNow, robots.txt, verification, RSS
  Compat/             SEO plugin conflict detection, Divi shim
  Cli/                WP-CLI commands
assets/               Admin/editor JS and CSS, sitemap XSL
languages/            .pot and it_IT translations
```

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) © Martini Multimedia
