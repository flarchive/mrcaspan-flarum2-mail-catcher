import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import darkEmail from '../utils/darkEmail';
import CatchModeModal from './CatchModeModal';
import LogModeModal from './LogModeModal';
import ForwardModeModal from './ForwardModeModal';
import ReleaseModal from './ReleaseModal';

const PREFIX = 'mrcaspan-flarum2-mail-catcher.admin.';
const t = (key, params) => app.translator.trans(PREFIX + key, params);

const api = (id) => app.forum.attribute('apiUrl') + '/mail-catcher' + (id ? '/' + id : '');

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '');

const STATUSES = ['sent', 'failed', 'caught'];
const statusBadge = (status) => (
  <span className={'MailCatcher-status MailCatcher-status--' + status}>{t('status_' + status)}</span>
);

// Whether the admin panel is showing in dark mode. Flarum puts the active theme
// ("light", "dark", "dark-hc", ...) on <html data-theme>; anything else is
// treated as "follow the system".
const isDarkTheme = () => {
  const theme = document.documentElement.getAttribute('data-theme') || '';
  if (theme.startsWith('dark')) return true;
  if (theme.startsWith('light')) return false;
  return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
};

export default class MailCatcherPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.messages = null;
    this.total = 0;
    this.stats = { sent: 0, failed: 0, caught: 0 };
    // { mode: 'off'|'catch'|'log', until: ISO|null, maxDays: 7 }
    this.mode = null;
    this.savingMode = false;
    this.loadingList = false;
    this.selectedId = null;
    this.selected = null;
    this.loadingMessage = false;
    // Note: don't name this `view` — that would overwrite the component's view() method.
    this.activeTab = 'html';
    // null = follow the admin theme; true/false = the admin flipped it.
    this.darkPreview = null;

    this.load();
  }

  // This extension has no permissions, so drop that section.
  sections(vnode) {
    const items = super.sections(vnode);
    items.remove('permissions');
    return items;
  }

  load() {
    this.loadingList = true;

    return app.request({ method: 'GET', url: api() }).then(
      (res) => {
        this.messages = res.data;
        this.total = res.total;
        this.stats = res.stats;
        this.applyMode(res.mode);
        this.loadingList = false;
        if (this.selectedId && !this.messages.some((msg) => msg.id === this.selectedId)) {
          this.selectedId = null;
          this.selected = null;
        }
        m.redraw();
      },
      () => {
        this.loadingList = false;
        m.redraw();
      }
    );
  }

  open(id) {
    this.selectedId = id;
    this.selected = null;
    this.loadingMessage = true;

    app.request({ method: 'GET', url: api(id) }).then(
      (res) => {
        if (this.selectedId !== id) return;
        this.selected = res;
        this.loadingMessage = false;
        if (this.activeTab === 'html' && !res.html) this.activeTab = 'text';
        m.redraw();
      },
      () => {
        this.loadingMessage = false;
        m.redraw();
      }
    );
  }

  applyMode(mode) {
    this.mode = mode;
  }

  // Catch and Log each ask first in a modal; Log's modal also picks the number of days.
  setMode(mode, confirmed = false, days = 0, to = '') {
    if (mode === 'catch' && this.mode.mode !== 'catch' && !confirmed) {
      app.modal.show(CatchModeModal, { onconfirm: () => this.setMode('catch', true) });
      return;
    }

    if (mode === 'log' && !confirmed) {
      app.modal.show(LogModeModal, { maxDays: this.mode.maxDays, onconfirm: (d) => this.setMode('log', true, d) });
      return;
    }

    if (mode === 'forward' && !confirmed) {
      app.modal.show(ForwardModeModal, { forwardTo: this.mode.forwardTo, onconfirm: (a) => this.setMode('forward', true, 0, a) });
      return;
    }

    this.savingMode = true;

    app
      .request({ method: 'POST', url: api('mode'), body: { mode, days, to } })
      .then(
        (res) => {
          this.applyMode(res);
          this.savingMode = false;
          m.redraw();
        },
        () => {
          this.savingMode = false;
          m.redraw();
        }
      );
  }

  release(msg) {
    app.modal.show(ReleaseModal, {
      message: msg,
      onreleased: (res) => {
        const sent = res.status === 'sent';
        app.alerts.show({ type: sent ? 'success' : 'error' }, t(sent ? 'released' : 'release_failed'));
        // Show the entry that records this delivery (a new one when sent to another address).
        this.load().then(() => this.open(res.id));
        this.activeTab = 'delivery';
      },
    });
  }

  remove(id) {
    app.request({ method: 'DELETE', url: api(id) }).then(() => {
      const removed = this.messages.find((msg) => msg.id === id);
      if (removed && this.stats[removed.status] > 0) this.stats[removed.status]--;
      this.messages = this.messages.filter((msg) => msg.id !== id);
      this.total = Math.max(0, this.total - 1);
      if (this.selectedId === id) {
        this.selectedId = null;
        this.selected = null;
      }
      m.redraw();
    });
  }

  clearAll() {
    if (!confirm(extractText(t('delete_all_confirm', { count: this.total })))) return;

    app.request({ method: 'DELETE', url: api() }).then(() => {
      this.messages = [];
      this.total = 0;
      this.stats = { sent: 0, failed: 0, caught: 0 };
      this.selectedId = null;
      this.selected = null;
      m.redraw();
    });
  }

  content() {
    return (
      <div className="ExtensionPage-settings MailCatcher">
        <div className="container">
          {this.modeBar()}

          <div className="MailCatcher-toolbar">
            <Button className="Button" icon="fas fa-sync-alt" loading={this.loadingList} onclick={() => this.load()}>
              {t('refresh_button')}
            </Button>
            <Button className="Button Button--primary" icon="fas fa-trash-alt" disabled={!this.total} onclick={() => this.clearAll()}>
              {t('delete_all_button')}
            </Button>
            <span className="MailCatcher-count">
              {t('count', { count: this.total })}
              {STATUSES.map((s) => (
                <span className={'MailCatcher-stat MailCatcher-stat--' + s}>{t('stat_' + s, { count: this.stats[s] || 0 })}</span>
              ))}
            </span>
          </div>

          <div className="MailCatcher-panes">
            <div className="MailCatcher-listWrap">{this.list()}</div>
            <div className="MailCatcher-viewer">{this.viewer()}</div>
          </div>
        </div>
      </div>
    );
  }

  modeBar() {
    if (!this.mode) return null;

    // With the "database" mail driver everything is caught and nothing can be sent.
    if (app.data.settings.mail_driver === 'database') {
      return (
        <div className="MailCatcher-mode">
          <div className="MailCatcher-warning">{t('driver_database', { b: <b />, a: <Link href={app.route('mail')} /> })}</div>
        </div>
      );
    }

    const current = this.mode.mode;

    const modeButton = (mode, icon) => (
      <Button
        className={classList('Button', { 'Button--primary': current === mode })}
        icon={icon}
        disabled={this.savingMode}
        onclick={() => this.setMode(mode)}
      >
        {t('mode_' + mode)}
      </Button>
    );

    return (
      <div className="MailCatcher-mode">
        <div className="MailCatcher-modeRow">
          <span className="MailCatcher-modeLabel">{t('mode_label')}</span>
          <div className="ButtonGroup">
            {modeButton('off', 'fas fa-power-off')}
            {modeButton('catch', 'fas fa-inbox')}
            {modeButton('log', 'fas fa-paper-plane')}
            {modeButton('forward', 'fas fa-share')}
          </div>
        </div>
        <p className="helpText MailCatcher-modeHelp">
          {t('help_' + current, { date: formatDate(this.mode.until), email: this.mode.forwardTo })}
        </p>
      </div>
    );
  }

  list() {
    if (this.messages === null) return <LoadingIndicator />;

    if (!this.messages.length) return <div className="MailCatcher-empty">{t('empty')}</div>;

    return (
      <ul className="MailCatcher-list">
        {this.messages.map((msg) => (
          <li key={msg.id} className={classList('MailCatcher-item', { active: this.selectedId === msg.id })} onclick={() => this.open(msg.id)}>
            <div className="MailCatcher-itemTop">
              <span className="MailCatcher-to">{msg.to}</span>
              <span className="MailCatcher-date">{formatDate(msg.created_at)}</span>
            </div>
            <div className="MailCatcher-itemBottom">
              <span className="MailCatcher-subject">{msg.subject || t('no_subject')}</span>
              {statusBadge(msg.status)}
            </div>
          </li>
        ))}
      </ul>
    );
  }

  viewer() {
    if (!this.selectedId) return <div className="MailCatcher-empty">{t('pick_one')}</div>;

    if (this.loadingMessage || !this.selected) return <LoadingIndicator />;

    const msg = this.selected;
    const tab = (key, disabled) => (
      <Button
        className={classList('Button', { 'Button--primary': this.activeTab === key })}
        disabled={disabled}
        onclick={() => (this.activeTab = key)}
      >
        {t('tab_' + key)}
      </Button>
    );

    const dark = this.darkPreview === null ? isDarkTheme() : this.darkPreview;

    let body;
    if (this.activeTab === 'html' && msg.html) {
      // Sandboxed iframe: no scripts run; links open in a new tab.
      body = (
        <iframe
          className={classList('MailCatcher-frame', { 'MailCatcher-frame--dark': dark })}
          sandbox="allow-popups allow-popups-to-escape-sandbox"
          srcdoc={'<base target="_blank">' + (dark ? darkEmail(msg.html) : msg.html)}
        />
      );
    } else if (this.activeTab === 'delivery') {
      body = this.delivery(msg);
    } else {
      const content = this.activeTab === 'raw' ? msg.raw : msg.text;
      body = <pre className="MailCatcher-pre">{content || t('empty_body')}</pre>;
    }

    return (
      <div className="MailCatcher-message">
        <div className="MailCatcher-head">
          <div className="MailCatcher-headSubject">{msg.subject || t('no_subject')}</div>
          <div className="MailCatcher-headButtons">
            {msg.status === 'caught' && app.data.settings.mail_driver !== 'database' ? (
              <Button className="Button" icon="fas fa-paper-plane" onclick={() => this.release(msg)}>
                {t('release_button')}
              </Button>
            ) : null}
            <Button className="Button Button--primary" icon="fas fa-trash-alt" onclick={() => this.remove(msg.id)}>
              {t('delete_button')}
            </Button>
          </div>
        </div>
        <dl className="MailCatcher-meta">
          <dt>{t('from_label')}</dt>
          <dd>{msg.from}</dd>
          <dt>{t('to_label')}</dt>
          <dd>{msg.to}</dd>
          <dt>{t('date_label')}</dt>
          <dd>{formatDate(msg.created_at)}</dd>
          <dt>{t('status_label')}</dt>
          <dd>{statusBadge(msg.status)}</dd>
        </dl>
        <div className="MailCatcher-tabs">
          {tab('html', !msg.html)}
          {tab('text', false)}
          {tab('raw', false)}
          {tab('delivery', false)}
          {this.activeTab === 'html' && msg.html ? (
            <Button
              className="Button MailCatcher-themeToggle"
              icon={dark ? 'fas fa-sun' : 'fas fa-moon'}
              onclick={() => (this.darkPreview = !dark)}
            >
              {t(dark ? 'show_light' : 'show_dark')}
            </Button>
          ) : null}
        </div>
        {body}
      </div>
    );
  }

  delivery(msg) {
    const row = (key, value) =>
      value === null || value === undefined || value === ''
        ? null
        : [<dt>{t(key)}</dt>, <dd>{value}</dd>];

    return (
      <div className="MailCatcher-delivery">
        <dl className="MailCatcher-meta">
          {row('status_label', statusBadge(msg.status))}
          {row('transport_label', msg.transport)}
          {row('message_id_label', msg.message_id)}
          {row('duration_label', msg.duration_ms === null ? null : t('duration', { ms: msg.duration_ms.toLocaleString() }))}
        </dl>
        {msg.error ? <pre className="MailCatcher-pre MailCatcher-error">{msg.error}</pre> : null}
        <div className="MailCatcher-debugTitle">{t('debug_label')}</div>
        <pre className="MailCatcher-pre">{msg.debug || t('no_debug')}</pre>
      </div>
    );
  }
}
