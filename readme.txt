=== MM SEO ===
Contributors: martinimultimedia
Tags: seo, sitemap, schema, redirects, meta
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Full-featured, lean SEO plugin. Titles, meta, schema, sitemaps, redirects, 404 monitor, and more — without the bloat.

== Description ==

MM SEO is a complete, standalone SEO plugin with its own analysis and scoring engine (the "MM Score") — not based on any other SEO plugin. It is built for sites that want powerful SEO without ads, upsells, or tracking. Every feature is modular — enable only what you need.

**Core features:**

* **Titles & Meta templates** — build title and description patterns using `%%title%%`, `%%sitename%%`, `%%sep%%`, `%%excerpt%%`, `%%date%%`, `%%author%%`, `%%term_title%%`, `%%page%%` and more, set per post type and taxonomy.
* **Open Graph & Twitter Cards** — automatic `og:*` and `twitter:*` meta tags with per-post overrides and a global fallback image.
* **JSON-LD Schema** — structured data for Article, TouristDestination, LodgingBusiness, TouristTrip, Restaurant, Event, FAQPage, BreadcrumbList, WebSite, Organisation/Person. Travel-specific types built in.
* **XML Sitemaps with images** — auto-generated sitemap index at `/sitemap_index.xml`, per-type sub-sitemaps, image entries, lastmod dates, standard-format URLs.
* **SEO score 0–100 with live analysis** — real-time checks in the Gutenberg sidebar and in the Divi/classic editor meta box: keyword in title, slug, meta description, first paragraph, subheadings; keyword density; title and description length; content length; internal & external links; image alt attributes; paragraph and sentence length.
* **Taxonomy SEO** — custom title, meta description, canonical and robots settings for every category, tag and custom taxonomy term.
* **Redirect manager** — 301, 302, and 307 redirects, regex support, managed from the WordPress admin.
* **404 Monitor** — log not-found requests with referrer and hit count; create a redirect in one click.
* **IndexNow** — ping Bing and Yandex the moment content is published or updated.
* **Breadcrumbs** — output with `[mmseo_breadcrumbs]` shortcode; full BreadcrumbList schema; customisable separator and home label.
* **Robots.txt editor** — manage your site's robots directives from the WordPress admin (virtual file; disabled automatically if a physical file exists).
* **Site verification** — Google Search Console, Bing Webmaster Tools, Yandex Webmaster, Pinterest meta tags.
* **RSS Optimizer** — prepend or append custom HTML to every RSS feed item.
* **WP-CLI commands** — `wp mmseo status`, `wp mmseo analyze [--all] [--post=<id>]`, `wp mmseo flush-sitemap`.
* **Conflict auto-pause** — when another SEO plugin (Yoast SEO, Rank Math, All in One SEO, SEOPress) is detected, MM SEO automatically pauses its frontend output to prevent duplicate meta tags. Use the "Force output" option in MM SEO → Advanced to override this if you know what you are doing.
* **Zero bloat** — no ads, no upsells, no external tracking, no data sent to third-party servers.

== Installation ==

1. Upload the `mm-seo` folder to `/wp-content/plugins/`, or install directly from the WordPress admin via **Plugins → Add New**.
2. Activate the plugin through the **Plugins** menu.
3. Go to **MM SEO → Dashboard** and enable the modules you want.
4. Configure title templates in **MM SEO → Titles & Meta**.
5. Optionally fill in social profiles and site identity under the **Dashboard** tab.
6. Visit **Settings → Permalinks** and click **Save Changes** to flush rewrite rules and activate `/sitemap_index.xml`.

== Going live (replacing another SEO plugin) ==

Follow these steps in order to migrate from your previous SEO plugin to MM SEO with zero downtime:

1. **Activate MM SEO** — while your previous SEO plugin is still active, MM SEO detects the conflict and stays in paused mode, outputting no tags, so there are no duplicate meta tags during the transition.
2. **Configure MM SEO** — visit **MM SEO → Dashboard** to enable the modules you need, then go to **MM SEO → Titles & Meta** and set your title/description templates, site identity, and social profiles.
3. **Re-enter per-post SEO data as needed** — MM SEO does not automatically import data from your previous SEO plugin. Open any post or page and fill in the SEO Title, Meta Description, and Focus Keyword in the MM SEO sidebar or meta box. (Posts without custom overrides will use the global templates automatically.)
4. **Deactivate your previous SEO plugin** — once your templates are in place, deactivate the other plugin from **Plugins**. MM SEO output (title tags, meta tags, Open Graph, JSON-LD, sitemaps) enables automatically as soon as no conflicting plugin is detected. If you need to run MM SEO alongside another SEO plugin temporarily, enable "Force output" in **MM SEO → Advanced** — but be aware this will produce duplicate tags until the other plugin is deactivated.
5. **Flush rewrite rules** — go to **Settings → Permalinks** and click **Save Changes**. This registers MM SEO's sitemap rewrite rules so `/sitemap_index.xml` resolves correctly.
6. **Verify page source** — open any post or page, view source, and confirm there is exactly **one** `<title>` tag, one `<meta name="description">`, one `<link rel="canonical">`, and one `<script type="application/ld+json">` block. There should be no leftovers from your previous SEO plugin.
7. **Check the sitemap** — visit `https://yoursite.com/sitemap_index.xml` and confirm it loads and lists your post-type sub-sitemaps.
8. **Update Google Search Console if needed** — MM SEO uses the sitemap URL format `/sitemap_index.xml`, so in most cases your existing Search Console sitemap submission continues to work without changes.
9. **Verify Divi compatibility** — if your site uses the Divi theme, confirm that Divi's built-in SEO options are disabled (**Divi → Theme Options → SEO → Enable Divi SEO: off**). MM SEO ships a compatibility shim that suppresses Divi's duplicate output automatically, but keeping Divi SEO disabled is the cleanest approach.

== Frequently Asked Questions ==

= I use the Divi Visual Builder. Can I still edit SEO fields? =

Yes. SEO fields (SEO Title, Meta Description, Focus Keyword, canonical, robots, schema) are set in **wp-admin**, not inside the Visual Builder canvas. Edit your post or page in the standard WordPress admin and you will find the MM SEO panel in the right-hand sidebar or below the editor. Changes made there apply instantly on the frontend.

= Where does the SEO score come from? =

The 0–100 score is calculated by MM SEO entirely on your server using its own built-in analysis engine (the "MM Score"). It evaluates the post content, your SEO title, meta description, focus keyword, and slug. Each check contributes a weighted number of points. The score is recalculated live in the Gutenberg sidebar as you type, and stored when you save the post. No content is sent to external services.

= Does MM SEO phone home or collect data? =

No. MM SEO does not send any data to Martini Multimedia or any third party. The only outbound requests are those you explicitly trigger: IndexNow pings to Bing/Yandex when you publish or update content (if the IndexNow module is enabled). Everything else runs entirely on your own server.

= Can I import SEO data from my previous plugin? =

MM SEO does not include an automatic importer in version 1.0.0. Per-post SEO data stored by other plugins is not read by MM SEO. You can re-enter custom overrides post by post, or leave them empty and rely on MM SEO's global title/description templates, which apply automatically to every post that has no custom override.

== Changelog ==

= 1.0.0 =
* Initial release.
* Titles & Meta templates with %%variable%% system.
* Open Graph and Twitter Card meta tags with per-post overrides.
* JSON-LD schema: Article, TouristDestination, LodgingBusiness, TouristTrip, Restaurant, Event, FAQPage, BreadcrumbList, WebSite, Organisation/Person.
* XML sitemaps with image entries and standard-format sitemap index URL.
* Real-time SEO score (0–100) with live analysis in Gutenberg sidebar and classic/Divi meta box (MM Score engine, built in-house).
* Taxonomy SEO (title, meta, canonical, robots per term).
* Redirect manager with 301/302/307 and regex support.
* 404 Monitor with one-click redirect creation.
* IndexNow integration (Bing + Yandex).
* Breadcrumbs shortcode `[mmseo_breadcrumbs]` with BreadcrumbList schema.
* Virtual robots.txt editor.
* Site verification meta tags (Google, Bing, Yandex, Pinterest).
* RSS Optimizer (prepend/append HTML to feed items).
* WP-CLI commands: `wp mmseo status`, `wp mmseo analyze`, `wp mmseo flush-sitemap`.
* Generic SEO plugin conflict detection (Yoast SEO, Rank Math, All in One SEO, SEOPress) with automatic output pause and Force output override.
* Divi ePanel SEO conflict detection and compatibility shim.
* Zero external tracking or ads.
