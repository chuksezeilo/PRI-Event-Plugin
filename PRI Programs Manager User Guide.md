# PRI Programs Manager: User Guide

Oct 30, 2026 · @Chuks

## Install

PRI Programs Manager lists your retreats, workshops and trainings as cards on any page, and emails you enquiries from a short form. Each program changes from Upcoming to Ongoing to Past by itself, using its dates.

1. In WordPress, go to Plugins > Add New > Upload Plugin, choose the zip file, click Install Now, then Activate.
2. Go to Settings > General and set Timezone to Lagos (UTC+1), so programs change status at midnight your time.
3. Go to Programs > Settings, enter the address that should receive enquiries, save, and click Send test email.

A Programs menu now appears in the left sidebar.

## Add a program

Go to Programs > Add New. Type the title, write the full description in the main text area, and fill in these boxes:

- **Program Details box:** start date (required), end date (leave empty for a one-day program), start time (optional), location, and event summary. The summary appears as "Summary" on the program page, so use it after the event for outcomes and highlights.
- **Excerpt:** the short description on the card. In the block editor it is in the right-hand panel under the Post tab. If you leave it empty, the card shows the first 28 words of the main text.
- **Featured image:** the card photo. A past program with no featured image uses the first photo from its gallery.
- **Program Types** (right-hand side): tick Retreat, Training, Workshop and so on. Each type becomes a filter button on the listing.

Click Publish. The status is never typed in; it comes from the dates:

| Status | When |
| --- | --- |
| Upcoming | Before the start date |
| Ongoing | From the start date to the end date, both included |
| Past | From the day after the end date |

The page is always right when someone opens it, and a daily check at 00:05 keeps the Programs list in step.

## Attach PDF documents

Each program can carry several PDFs, such as a syllabus, agenda or case study; visitors download them from the card and the program page.

1. Open the program and find the **Program Documents (PDF)** box.
2. Click + **Add PDF**, then upload or pick a PDF in the Media Library and click **Use this PDF**.
3. Type a label (for example "Syllabus"). Drag rows to change the order.
4. Click **Update**.

Only real PDF files are accepted. Use **Remove** to detach a PDF from the program; the file stays in your Media Library.

## Past programs: photos, videos and summary

A program turns Past by itself the day after its end date. Photos and videos show only on Past programs, so you can prepare them in advance.

1. Fill in the **Summary** field in the **Program Details** box; it appears as the Summary section on the program page.
2. In **Event Gallery (photos and videos)**, click **+ Add photos** and select many photos at once. Drag to reorder.
3. Click **+ Add video** and paste a YouTube or Vimeo link, or choose **Choose a video file** to use a file from the Media Library.
4. Click **Update**.

Only YouTube and Vimeo links are accepted. If a link is not valid, a red warning appears under it as you paste and the link is not saved.

On the listing, a Past card shows a **View Gallery & Stories** button with the photo and video counts. When the program has no featured image, the first gallery photo is used for the card.

## Show programs on a page

Add a Shortcode block to any page and type `[pri_programs]`. It shows a card grid with filter buttons and a **Load More** button. Ongoing programs come first, then upcoming (soonest first), then past (most recent first).

All options are optional:

| Option | What it does | Example |
| --- | --- | --- |
| status | Show only some statuses: all, upcoming, ongoing, past (comma list allowed) | `[pri_programs status="upcoming,ongoing"]` |
| type | Show only these program types (slugs, comma separated) | `[pri_programs type="retreat,workshop"]` |
| limit | Cards shown first and added by each Load More (default 6) | `[pri_programs limit="9"]` |
| filters | Show the filter buttons: yes or no (default yes) | `[pri_programs filters="no"]` |
| default | Filter selected when the page opens: all, a status or a type slug | `[pri_programs default="upcoming"]` |

Filter buttons only appear for statuses and types that exist, so there is never an empty filter. Up to 60 programs are listed on one page.

## The enquiry form

Upcoming and ongoing program pages end with an enquiry form. **Book Your Spot** buttons, on the cards and on the program page, jump straight to it. There are no payments: the visitor's details are emailed to you and you follow up.

- **Where the email goes:** the address on the program's **Enquiry email** box if you filled it in, otherwise the address in Programs > Settings, otherwise the site admin email.
- **Replying:** the Reply-To of each email is the visitor, so you answer with one click.
- **Past programs** have no form.
- **A stand-alone form:** type `[pri_enquiry]` on any page. It includes a dropdown to choose the program.

### Spam protection

No setup needed, and it works with page caching:

- a hidden trap field that only bots fill in
- a signed timestamp: forms sent in under 3 seconds are rejected
- a limit of 8 enquiries per visitor address per hour
- messages with more than 2 links are rejected

## Settings

Open **Programs > Settings**.

| Setting | What it does |
| --- | --- |
| Send enquiries to | Default email for enquiries. A program's own **Enquiry email** overrides it. |
| Thank-you message | Shown after the form is sent; leave empty for the default. |
| Main button colours | Start and end colour of the **Book Your Spot** and **Send Enquiry** buttons, with a live preview. Choose the same colour twice for a solid button. |
| Gallery button colour | The **View Gallery & Stories** button on past programs. |
| Reset all colours | Tick it and save to return to the default colours. |
| When the plugin is deleted | Off by default. If ticked, deleting the plugin also removes all programs, program types and settings. |

Use **Send test email** at the bottom of the page to check that email reaches you.

Photos, videos and PDFs always stay in the Media Library. Deactivating the plugin never deletes anything.

## Update to a new version

1. Go to **Plugins > Add New > Upload Plugin** and choose the new zip.
2. Click **Install Now**. WordPress notices the plugin is already installed.
3. Click **Replace current with uploaded**.
4. Clear your cache (SpeedyCache) so visitors see the new styles.

Your programs, settings and files are kept.

**Never click Delete on the plugin while "delete all data" is ticked in Programs > Settings.** Deleting then removes every program. Using **Replace current with uploaded** is safe either way.

## Troubleshooting

| Problem | What to do |
| --- | --- |
| Enquiry emails do not arrive | Use **Send test email** in Programs > Settings and check the spam folder. If the test fails, your hosting blocks PHP mail: install an SMTP plugin such as WP Mail SMTP. |
| A page still shows old content or styles | Clear the SpeedyCache cache, then reload. |
| Cards or program pages look unstyled | Clear the cache. Check that the page contains the shortcode and that the plugin is active. |
| A program page shows "Page not found" | Go to **Settings > Permalinks** and click **Save Changes** once. |
| A video does not show | Only YouTube and Vimeo links and uploaded video files work. Photos and videos show only once the program is Past. |
| A program is in the wrong group | Status follows the start and end dates. Check them in **Program Details**; with no end date the program is a one-day event. |
