import app from 'flarum/admin/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';

const t = (key, params) => app.translator.trans('mrcaspan-flarum2-mail-catcher.admin.' + key, params);

/**
 * Explains Forward mode and asks for the address all email goes to.
 * attrs: forwardTo (last used address), onconfirm(address)
 */
export default class ForwardModeModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);
    this.to = this.attrs.forwardTo || '';
  }

  className() {
    return 'MailCatcher-forwardModal Modal--small';
  }

  title() {
    return t('forward_modal_title');
  }

  content() {
    return (
      <div className="Modal-body">
        <p>{t('forward_modal_text')}</p>
        <p>{t('forward_modal_settings')}</p>
        <p>
          <b>{t('forward_modal_warning')}</b>
        </p>
        <div className="Form-group">
          <label>{t('forward_modal_to_label')}</label>
          <input
            className="FormControl"
            type="email"
            name="to"
            required
            placeholder={t('release_other_placeholder')}
            value={this.to}
            oninput={(e) => (this.to = e.target.value)}
          />
        </div>
        <div className="MailCatcher-modalButtons">
          <Button className="Button Button--primary" type="submit" icon="fas fa-share">
            {t('forward_modal_confirm')}
          </Button>
        </div>
      </div>
    );
  }

  onsubmit(e) {
    e.preventDefault();
    const to = this.to.trim();
    if (!to) return;

    this.hide();
    this.attrs.onconfirm(to);
  }
}
