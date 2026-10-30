=== PRI Programs Manager ===
Author: Chuks Ezeilo
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 0.6.1
License: GPLv2 or later

Organise programs, retreats and workshops with automatic Upcoming / Ongoing / Past status.

== Changelog ==

= 0.6.1 =
* Fix: a bad video link now shows a red warning right under the link field as you type or paste (and a notice after saving in the block editor), instead of silently not saving

= 0.6.0 =
* Programs > Settings: button colours (with live preview and reset), "delete all data on uninstall" option
* Program page: details panel (status, date, time, location, type) with Book Your Spot button
* Section headings (Summary, Gallery, Downloads, Enquire) now styled consistently on any theme
* uninstall.php: removes the daily job always; removes programs, types and settings only if the option is ticked

= 0.5.0 =
* Event Gallery box: add many photos at once (drag to reorder) and videos (YouTube link, Vimeo link or uploaded video file)
* Past program pages show the event summary, a photo grid with lightbox, and a video grid
* Past cards show photo/video counts and use the first gallery photo when there is no featured image
* Only YouTube/Vimeo links are accepted; a rejected link is reported after saving

= 0.4.0 =
* Enquiry form ("Book Your Spot") on upcoming and ongoing program pages; emails the enquiry from the plugin with Reply-To set to the visitor
* Programs > Settings: recipient email, thank-you message, Send test email button
* Spam protection (cache-friendly, no nonce): hidden honeypot field, signed timestamp, per-IP hourly limit, cap on links in a message
* Optional per-program recipient address ("Enquiry email" box on the program edit screen)
* [pri_enquiry] shortcode for a stand-alone form (with a program dropdown)
* Book Your Spot buttons on cards now lead to the form

= 0.3.0 =
* [pri_programs] shortcode: card grid with filter pills (All, status, program types) and Load More
* Attributes: status, type, limit, filters, default
* Cards show image, status badge, dates, location, short description, Book Your Spot / Learn More / View Gallery & Stories buttons and PDF links
* Program pages now show status, dates, location, summary and downloads

= 0.2.0 =
* Program Documents box: attach multiple PDFs per program with labels (Syllabus, Agenda, Case Study...)
* Drag to reorder; PDFs come from the Media Library; only real PDF files are accepted
* PDFs count column on the Programs list

= 0.1.0 =
* Programs post type with Program Types taxonomy
* Program Details box: start/end date, start time, location, summary
* Automatic status (Upcoming, Ongoing, Past) from dates, refreshed daily
* Status, dates and location columns on the Programs list
