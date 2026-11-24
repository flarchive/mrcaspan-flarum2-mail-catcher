# Mail Catcher for Flarum 2.0

![License](https://img.shields.io/badge/license-MIT-blue.svg) ![Flarum](https://img.shields.io/badge/flarum-2.0-orange.svg)

Catch every email your forum sends and read it in the admin panel. **Nothing is delivered.**

It's for test, staging and development copies of a Flarum forum. You can test sign-up, password reset, notification and other emails without setting up SMTP or a third-party sandbox, and without any real user getting an email.

> [!WARNING]
> **Don't use this on a live forum.** While the `database` mail driver is selected, nobody receives *any* email. That includes account confirmations and password resets.

## Features

- Adds a **`database`** mail driver to *Admin → Email*. Every outgoing message is stored in your forum's database and never sent.
- An inbox on the extension's admin page:
  - a list of caught emails (newest first) showing the recipient, date and subject
  - a viewer with **HTML** (shown as the recipient would see it, in a sandboxed frame), **Text** and **Raw** (full message with headers) tabs
  - **Delete** a single email, or **Delete all**
  - a warning when the mail driver isn't set to `database`, so you know mail isn't being caught
- The admin page and API are only available to administrators.
- Works with light and dark mode.
- No settings and no outside services. The emails stay on your server.

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
2. Go to *Admin → Email* and set **Mail Driver** to **`database`**, then save.
3. Do something that sends an email (or use *Send test email*), then open *Admin → Mail Catcher* to read it.

## Updating

```sh
composer update mrcaspan/flarum2-mail-catcher
php flarum migrate
php flarum cache:clear
```

## Uninstalling

1. In *Admin → Email*, switch the mail driver back to a real one (SMTP, Mailgun, etc.).
2. Disable the extension. To also drop its table, run `php flarum migrate:reset --extension=mrcaspan-flarum2-mail-catcher`.
3. Run `composer remove mrcaspan/flarum2-mail-catcher`.

## How it works

The extension registers a Symfony Mailer transport through Flarum's `Extend\Mail` extender. Instead of connecting to a mail server, the transport writes each message to the `mail_catcher_messages` table: from, to, subject, HTML body, text body, raw message and time (UTC). The admin page reads that table through a small admin-only API:

| Method   | Endpoint                 | Does                     |
| -------- | ------------------------ | ------------------------ |
| `GET`    | `/api/mail-catcher`      | List (newest 500)        |
| `GET`    | `/api/mail-catcher/{id}` | One email with its bodies |
| `DELETE` | `/api/mail-catcher/{id}` | Delete one               |
| `DELETE` | `/api/mail-catcher`      | Delete all               |

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

This extension was written entirely with **[Claude Code](https://claude.com/claude-code)** (Anthropic's AI coding assistant), and tested on a real Flarum 2.0 forum. Issues and pull requests are welcome.

## License

[MIT](LICENSE)
