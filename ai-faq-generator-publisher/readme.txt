=== AI FAQ Generator & Publisher ===
Contributors: customplugin
Tags: faq, accordion, ai, openai, gemini, schema
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later

Generate FAQs with AI, edit & publish, display with a smooth slide accordion + FAQPage schema.

== Description ==

* Generate FAQ sets from a topic using OpenAI or Gemini
* Edit questions/answers in a polished admin dashboard
* Publish and embed with `[ai_faq id="123"]`
* Frontend accordion with smooth slide up / down
* Optional FAQPage JSON-LD for SEO

== Installation ==

1. Upload the `ai-faq-generator-publisher` folder to `/wp-content/plugins/`
2. Activate **AI FAQ Generator & Publisher**
3. Open **AI FAQ** in wp-admin
4. Add an API key under Settings
5. Generate → Publish → paste the shortcode

== Shortcode ==

`[ai_faq id="123"]`

Optional attributes:

* `title="Custom heading"`
* `open_first="1"`
* `allow_multiple="0"`
* `schema="1"`

== Changelog ==

= 1.0.2 =
* Updated dashboard typography (Sora + Plus Jakarta Sans) and visual consistency

= 1.0.1 =
* Redesigned admin dashboard (charcoal rail + light workspace)

= 1.0.0 =
* Initial release
