=== Eventbilletten ===
Contributors: eventbilletten, lasseenggaard
Tags: events, tickets, event list, event schema, shortcode
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Event list and ticket sales plugin for WordPress. Shows Eventbilletten events and manual events with filters, ticket buttons and Event schema.

== Description ==

Eventbilletten is a WordPress events plugin that shows a list of upcoming events with ticket buttons on any page or post. Events are fetched from your Eventbilletten ticket system or created by hand in WordPress, and the list is inserted with the shortcode `[eventbilletten]`.

The plugin is built for sports clubs, associations, venues, festivals, speakers and other organisers in Denmark who sell tickets online and want the event calendar on their own website to stay up to date.

= Features =

* Shows events from your Eventbilletten JSON feed with date, time, venue, address, image and description
* Updates the event list once an hour, so changes in the ticket system reach the website without retyping
* Lets you create manual events in WordPress with title, date, time, venue, image, description, category and ticket link
* Shows feed events and manual events in one list, sorted by date or in your own drag-and-drop order
* List layout or grid layout with 2, 3 or 4 columns, adapted to tablet and mobile
* Region filter for the Danish regions Hovedstaden, Sjælland, Fyn, Nordjylland, Midtjylland and Sydjylland, found from the city of the event
* Category filter, with categories from the feed and your own categories
* "Read more" popup with the event description, or a link to a page of your own
* Ticket button that opens the Eventbilletten ticket shop in a window on your own page
* Your own button text and your own colours for button, title and date
* Event schema (JSON-LD) with date, venue, address, image and ticket link for Google and AI search
* Hides past events automatically, and lets you hide a manual event without deleting it
* Only loads CSS and JavaScript on pages that use the shortcode

= What can you use it for? =

* An event calendar on a club, venue or association website
* A concert, festival or lecture list with ticket sales
* A list of events in one region or one category on a landing page
* Events that are not sold through Eventbilletten, shown in the same list as the ones that are

Important: the plugin does not sell tickets on its own. Ticket sales and payment are handled by Eventbilletten. Without an Eventbilletten account you can still create manual events and link the ticket button to any ticket page.

= Shortcode =

* `[eventbilletten]` shows all events
* `[eventbilletten region="fyn"]` shows events in one region
* `[eventbilletten category="Koncert"]` shows events in one category
* `[eventbilletten region="sydjylland" category="Koncert"]` combines region and category
* `[eventbilletten layout="grid" columns="2"]` shows a grid with two columns
* `[eventbilletten layout="list"]` shows a list no matter what the setting is

Region values: `hovedstaden`, `sjaelland`, `fyn`, `nordjylland`, `midtjylland`, `sydjylland`.

= Event schema and SEO =

Every event in the list is printed as schema.org Event markup in JSON-LD. The markup holds the event name, start date and time, venue name, postal address, image, a short description and the ticket link as an Offer. That gives search engines and AI assistants the same facts as your visitors see. Event schema can be turned off in the settings if another plugin already prints it.

= About Eventbilletten =

Eventbilletten is an all-in-one ticket system for your events. Eventbilletten takes care of the technology, so you can concentrate on selling tickets to your guests. Handling and planning of your events is kept simple, which leaves you time to give your participants an experience they will remember.

Eventbilletten is used for:

* [Tastings](https://www.eventbilletten.dk/branche/smagninger)
* [Sports clubs](https://www.eventbilletten.dk/branche/sportsklub)
* [Associations](https://www.eventbilletten.dk/branche/forening)
* [Schools and education](https://www.eventbilletten.dk/branche/skoler-uddannelser)
* [Conferences](https://www.eventbilletten.dk/branche/konference)
* [Musicians and speakers](https://www.eventbilletten.dk/branche/musiker-foredragsholder)
* [Concerts and festivals](https://www.eventbilletten.dk/branche/koncert-festivaler)

= Who made the plugin? =

Eventbilletten for WordPress is developed by [Lasse Enggaard](https://lasse-enggaard.dk/om), SEO specialist, developer and partner at [Webro](https://webro.dk), a Danish agency with offices in Odense and Aabenraa. The plugin is made in cooperation with [Eventbilletten](https://www.eventbilletten.dk), a Danish ticket system for events.

= Compatibility =

The admin pages and the text shown to visitors are in Danish. The plugin works with the block editor, the classic editor and page builders that support shortcodes. Styling is kept to the event list, so the list follows the width of the theme's content area.

= Support =

Contact Lasse Enggaard at Webro with questions or bug reports at len@webro.dk, or use the support forum on WordPress.org. Questions about ticket sales, payment and feed URLs go to Eventbilletten.

== External services ==

The plugin connects to external services only after you have pasted an Eventbilletten feed URL in the settings. If you only use manual events, no external service is contacted.

= Eventbilletten JSON feed =

The plugin fetches your list of events from the feed URL you have pasted. The request is made from your web server once an hour, the first time a page with the shortcode is shown after the cache has expired. No information about your visitors is sent. The request contains only the feed URL and the standard information WordPress sends with an HTTP request, such as your site address in the user agent.

The service is provided by [Eventbilletten](https://www.eventbilletten.dk).

= Vivenu ticket shop =

The Eventbilletten ticket shop runs on Vivenu. When a feed URL is saved, pages with the shortcode load the script `https://vivenu.com/web/deliver/js/v1/embed.js` in the visitor's browser. The script opens the ticket shop in a window on your own page when a visitor clicks the ticket button. Loading the script sends the visitor's IP address and browser information to Vivenu. The script is not loaded when no feed URL is saved.

The service is provided by [Vivenu](https://vivenu.com).

== Installation ==

1. Install the plugin from the Plugins screen in WordPress and activate it.
2. Go to Eventbilletten in the admin menu.
3. Paste your feed URL from Eventbilletten, or create events under "Tilføj manuelt event".
4. Choose layout, filters, button text and colours under "Indstillinger".
5. Insert the shortcode `[eventbilletten]` on a page or in a post.

== Frequently Asked Questions ==

= Do I need an account with Eventbilletten? =

No. Manual events work without an account. You need an agreement with Eventbilletten to get a feed URL and show events from the ticket system automatically.

= How do I show events on a WordPress page? =

Insert the shortcode `[eventbilletten]` in a shortcode block, in the classic editor or in a shortcode widget in your page builder.

= How often is the event list updated? =

Once an hour. You can clear the cache under Eventbilletten in the admin menu to fetch the feed again right away.

= Can I show events from one region or category only? =

Yes. Use `[eventbilletten region="fyn"]` or `[eventbilletten category="Koncert"]`. The matching filter buttons are hidden when you do.

= Does the plugin add Event schema? =

Yes. Each event is printed as schema.org Event markup in JSON-LD with date, venue, address, image and ticket link. It is on by default and can be turned off in the settings.

= Does the plugin slow down my site? =

CSS and JavaScript are only loaded on pages with the shortcode. The feed is cached for an hour, and a feed that is down is not requested again for five minutes.

= What happens to my data if I delete the plugin? =

All settings and manual events are removed from the database when the plugin is deleted.

= Who do I contact for help? =

Write to Lasse Enggaard at Webro at len@webro.dk about the plugin. Contact Eventbilletten about your ticket account.

== Changelog ==

= 1.0.0 =
* First release.
