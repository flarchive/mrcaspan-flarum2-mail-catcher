# Mail Catcher for Flarum 2.0

![License](https://img.shields.io/badge/license-MIT-blue.svg) ![Flarum](https://img.shields.io/badge/flarum-2.0-orange.svg)

Control what happens to the emails your forum sends, and read them in the admin panel. **Catch** them (nothing is delivered), **log** them (delivered as normal, with a copy and the delivery result kept for diagnosing problems) or **forward** them all to one address.

## Modes

Pick the mode at the top of *Admin → Mail Catcher*. Catch, Log and Forward each explain themselves in a confirmation window before they switch on.

| Mode | What happens | Use it for |
| ---- | ------------ | ---------- |
| **Off** | Email is delivered normally. Nothing is saved. | Normal running |
| **Catch** | Every email is saved here and **nothing is sent**. Stays on until you switch it off. | Test, staging and development sites |
| **Log** | Every email is delivered with your real mail driver **and** a copy is saved with its delivery result. Runs for 1–7 days, then switches itself off. | Diagnosing email problems on a live forum |
| **Forward** | Every email is redirected to **one address you choose** instead of its recipients. Nothing is saved. Stays on until you switch it off. | Seeing real emails in your own inbox on a test site |

Your mail driver and SMTP settings are never changed by any mode.

> [!WARNING]
> **Catch and Forward on a live forum stop members getting any email**, including account confirmations and password resets. On a live forum use **Log**.

A forwarded email is sent exactly as written, so its *To:* line still shows the member it was meant for. Only the delivery goes to your address.

### Releasing a caught email

Open a caught email and click **Release** to send it after all, through your real mail driver. You can send it to its original recipient or to a different address. Released emails get the same delivery details as Log (status, message ID, server conversation). Release needs a real mail driver, so it isn't available with the `database` driver.

Log records, for each email:

- **Status:** sent or failed
- **Error message** when it failed (e.g. SMTP login refused, recipient rejected, connection timed out)
- **Message ID** given by the mail server or provider, to look up in its logs
- **Time taken**
- **Server conversation** (the SMTP transcript). Login lines are replaced with `[hidden]` so your mail password is never stored.

Saving the copy never stops an email from going out: if the log can't be written, the email is still sent.

Emails are kept until you delete them. Log captures the full email, so clear the inbox when you've finished diagnosing.

## Features

- Works with **any mail driver** (SMTP, Mailgun, Postmark, sendmail...). It wraps the driver's transport, so you keep your normal mail settings.
- Also adds a **`database`** mail driver to *Admin → Email*. With it selected, every email is caught whatever the mode (how 1.x worked).
- An inbox on the extension's admin page:
  - totals: how many emails, and how many sent, failed and caught
  - a list (newest first) showing the recipient, date, subject and status
  - a viewer with **HTML** (shown as the recipient would see it, in a sandboxed frame), **Text**, **Raw** (full message with headers) and **Delivery** tabs
  - **Release** a caught email, **Delete** a single email, or **Delete all**
- The admin page and API are only available to administrators.
- Works with light and dark mode. In dark mode the HTML tab shows the email dark too, the way Gmail and Outlook darken emails (colours flipped, images untouched), with a **Light/Dark** button to switch.
- No outside services. The emails stay on your server.

## Requirements

- Flarum **2.0** or later
- PHP 8.2+ (whatever your Flarum 2.0 install needs)

## Installation

```sh
composer require mrcaspan/flarum2-mail-catcher
php flarum migrate
php flarum cache:clear
```

Then:

1. Go to *Admin → Extensions → Mail Catcher* and enable it.
2. Open *Admin → Mail Catcher* and pick a mode: **Catch** or **Forward** on a test site, or **Log** (with a number of days) on a live one.
3. Do something that sends an email (or use *Send test email* in *Admin → Email*), then refresh the Mail Catcher page to read it.

## Updating

```sh
composer update mrcaspan/flarum2-mail-catcher
php flarum migrate
php flarum cache:clear
```

## Uninstalling

1. Set the mode to **Off**. If you used the `database` driver, switch *Admin → Email* back to a real one (SMTP, Mailgun, etc.).
2. Disable the extension. To also drop its table, run `php flarum migrate:reset --extension=mrcaspan-flarum2-mail-catcher`.
3. Run `composer remove mrcaspan/flarum2-mail-catcher`.

## How it works

A service provider wraps the Symfony Mailer transport that your mail driver builds. For each email it reads the current mode from the settings table:

- **Off:** hands the email straight to the real transport.
- **Catch:** saves it and returns without sending.
- **Log:** sends it with the real transport, then saves it with the result. A failure is saved and then passed on to Flarum unchanged.
- **Forward:** sends it with the real transport to the forward address only (the SMTP envelope recipient is replaced; the message itself is unchanged).

Emails go into the `mail_catcher_messages` table: from, to, subject, HTML body, text body, raw message, status, transport, message ID, error, server conversation, time taken and date (UTC). The admin page uses a small admin-only API:

| Method   | Endpoint                 | Does                                           |
| -------- | ------------------------ | ---------------------------------------------- |
| `GET`    | `/api/mail-catcher`      | List (newest 500), totals and current mode     |
| `GET`    | `/api/mail-catcher/{id}` | One email with its bodies and delivery details |
| `DELETE` | `/api/mail-catcher/{id}` | Delete one                                     |
| `DELETE` | `/api/mail-catcher`      | Delete all                                     |
| `POST`   | `/api/mail-catcher/{id}/release` | Send a caught email: `{"to": "optional other address"}` |
| `POST`   | `/api/mail-catcher/mode` | Set the mode: `{"mode": "off"/"catch"/"log"/"forward", "days": 1-7, "to": "forward address"}` |

Emails are kept until you delete them.

## Development

The compiled JavaScript (`js/dist`) is committed. To change the admin page:

```sh
cd js
npm install
npm run build      # or: npm run dev
```

Translations are in `resources/locale/en.yml`. Pull requests that add other languages are welcome.

## Credits

Built by [MrCaspan](https://github.com/MrCaspan) for [PokerForum.ca](https://pokerforum.ca).

**[Claude Code](https://claude.com/claude-code)** (Anthropic's AI coding assistant) was used to help vibe code this extension. It was tested on a real Flarum 2.0 forum. Issues and pull requests are welcome.

## License

[MIT](LICENSE)
