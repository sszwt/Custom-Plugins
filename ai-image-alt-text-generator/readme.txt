=== AI Image ALT Text Generator ===
Contributors: medgrowth
Tags: alt text, accessibility, ai, images, seo, gemini, openai
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Generate accessible ALT text for Media Library images using Google Gemini or OpenAI vision.

== Description ==

AI Image ALT Text Generator helps you improve **accessibility** and **SEO** by automatically creating ALT text for images in your WordPress Media Library.

**Features:**
* AI vision with **Google Gemini** (gemini-2.5-flash) or **OpenAI** (GPT-4o)
* Works on **localhost** — reads image files directly (no public URL needed)
* **Bulk generate** all missing ALT text from Settings
* **Media Library** bulk action + per-image Generate button
* **ALT Text column** in Media Library list view
* **Auto-generate on upload** (optional)
* **Fallback mode** using filename/title when no API key
* Multi-language support (en, hi, es, etc.)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu
3. Go to **Settings → AI Image ALT Text**
4. Add your Gemini or OpenAI API key
5. Click **Test API Key**, then **Generate ALT for All Missing Images**

== Changelog ==

= 1.1.0 =
* Added Google Gemini support
* Fixed vision on local/dev sites (base64 file upload)
* Media Library ALT Text column
* Test API Key button
* Improved bulk generation

= 1.0.0 =
* Initial release
