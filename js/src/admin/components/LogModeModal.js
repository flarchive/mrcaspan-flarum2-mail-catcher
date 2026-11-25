import app from 'flarum/admin/app';
import FormModal from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';

const t = (key, params) => app.translator.trans('mrcaspan-flarum2-mail-catcher.admin.' + key, params);

/**
 * Explains Log mode and picks how many days to run it for (1 to maxDays, default 1).
 * attrs: maxDays, onconfirm(days)
 */
export default class LogModeModal extends FormModal {
  oninit(vnode) {
    super.oninit(vnode);
    this.days = '1';
  }

  className() {
    return 'MailCatcher-logModal Modal--small';
  }

  title() {
    return t('log_modal_title');
  }

  content() {
    const options = {};
    for (let d = 1; d <= this.attrs.maxDays; d++) options[d] = t('days', { count: d });

    return (
      <div className="Modal-body">
        <p>{t('log_modal_text')}</p>
        <p>{t('log_modal_space')}</p>
        <div className="Form-group">
          <label>{t('log_modal_days_label')}</label>
          <Select options={options} value={this.days} onchange={(value) => (this.days = value)} />
        </div>
        <div className="MailCatcher-modalButtons">
          <Button className="Button Button--primary" type="submit" icon="fas fa-paper-plane">
            {t('log_modal_confirm')}
          </Button>
        </div>
      </div>
    );
  }

  onsubmit(e) {
    e.preventDefault();

    this.hide();
    this.attrs.onconfirm(parseInt(this.days, 10));
  }
}
