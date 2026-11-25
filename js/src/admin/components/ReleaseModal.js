import app from 'flarum/admin/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';

const t = (key, params) => app.translator.trans('mrcaspan-flarum2-mail-catcher.admin.' + key, params);

/**
 * Confirms releasing a caught email, to its original recipient or to another address.
 * attrs: message ({ id, to }), onreleased(result)
 */
export default class ReleaseModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);
    this.other = '';
  }

  className() {
    return 'MailCatcher-releaseModal Modal--small';
  }

  title() {
    return t('release_modal_title');
  }

  content() {
    const other = this.other.trim();

    return (
      <div className="Modal-body">
        <p>
          {other
            ? t('release_modal_text_other', { email: <b>{other}</b> })
            : t('release_modal_text', { email: <b>{this.attrs.message.to}</b> })}
        </p>
        <div className="Form-group">
          <label>{t('release_other_label')}</label>
          <input
            className="FormControl"
            type="email"
            name="to"
            placeholder={t('release_other_placeholder')}
            value={this.other}
            disabled={this.loading}
            oninput={(e) => (this.other = e.target.value)}
          />
        </div>
        <div className="MailCatcher-modalButtons">
          <Button className="Button Button--primary" type="submit" icon="fas fa-paper-plane" loading={this.loading}>
            {t('release_modal_confirm')}
          </Button>
        </div>
      </div>
    );
  }

  onsubmit(e) {
    e.preventDefault();
    this.loading = true;

    const id = this.attrs.message.id;
    const body = this.other.trim() ? { to: this.other.trim() } : {};

    app
      .request({ method: 'POST', url: app.forum.attribute('apiUrl') + '/mail-catcher/' + id + '/release', body })
      .then(
        (res) => {
          this.hide();
          this.attrs.onreleased(res);
        },
        (error) => {
          this.loading = false;
          this.onerror(error);
          m.redraw();
        }
      );
  }
}
