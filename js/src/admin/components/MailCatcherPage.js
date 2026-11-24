import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';

const PREFIX = 'mrcaspan-flarum2-mail-catcher.admin.';
const t = (key, params) => app.translator.trans(PREFIX + key, params);

const api = (id) => app.forum.attribute('apiUrl') + '/mail-catcher' + (id ? '/' + id : '');

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString() : '');

export default class MailCatcherPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.messages = null;
    this.total = 0;
    this.loadingList = false;
    this.selectedId = null;
    this.selected = null;
    this.loadingMessage = false;
    // Note: don't name this `view` — that would overwrite the component's view() method.
    this.activeTab = 'html';

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

  remove(id) {
    app.request({ method: 'DELETE', url: api(id) }).then(() => {
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
      this.selectedId = null;
      this.selected = null;
      m.redraw();
    });
  }

  content() {
    const driver = app.data.settings.mail_driver;

    return (
      <div className="ExtensionPage-settings MailCatcher">
        <div className="container">
          {driver === 'database' ? (
            <p className="helpText">{t('intro')}</p>
          ) : (
            <div className="MailCatcher-warning">{t('not_catching', { driver, b: <b />, a: <Link href={app.route('mail')} /> })}</div>
          )}

          <div className="MailCatcher-toolbar">
            <Button className="Button" icon="fas fa-sync-alt" loading={this.loadingList} onclick={() => this.load()}>
              {t('refresh_button')}
            </Button>
            <Button className="Button Button--primary" icon="fas fa-trash-alt" disabled={!this.total} onclick={() => this.clearAll()}>
              {t('delete_all_button')}
            </Button>
            <span className="MailCatcher-count">{t('count', { count: this.total })}</span>
          </div>

          <div className="MailCatcher-panes">
            <div className="MailCatcher-listWrap">{this.list()}</div>
            <div className="MailCatcher-viewer">{this.viewer()}</div>
          </div>
        </div>
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
            <div className="MailCatcher-subject">{msg.subject || t('no_subject')}</div>
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

    let body;
    if (this.activeTab === 'html' && msg.html) {
      // Sandboxed iframe: no scripts run; links open in a new tab.
      body = <iframe className="MailCatcher-frame" sandbox="allow-popups allow-popups-to-escape-sandbox" srcdoc={'<base target="_blank">' + msg.html} />;
    } else {
      const content = this.activeTab === 'raw' ? msg.raw : msg.text;
      body = <pre className="MailCatcher-pre">{content || t('empty_body')}</pre>;
    }

    return (
      <div className="MailCatcher-message">
        <div className="MailCatcher-head">
          <div className="MailCatcher-headSubject">{msg.subject || t('no_subject')}</div>
          <Button className="Button Button--primary" icon="fas fa-trash-alt" onclick={() => this.remove(msg.id)}>
            {t('delete_button')}
          </Button>
        </div>
        <dl className="MailCatcher-meta">
          <dt>{t('from_label')}</dt>
          <dd>{msg.from}</dd>
          <dt>{t('to_label')}</dt>
          <dd>{msg.to}</dd>
          <dt>{t('date_label')}</dt>
          <dd>{formatDate(msg.created_at)}</dd>
        </dl>
        <div className="MailCatcher-tabs">
          {tab('html', !msg.html)}
          {tab('text', false)}
          {tab('raw', false)}
        </div>
        {body}
      </div>
    );
  }
}
