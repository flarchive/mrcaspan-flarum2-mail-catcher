import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

const t = (key) => app.translator.trans('mrcaspan-flarum2-mail-catcher.admin.' + key);

/**
 * Asks before switching to Catch mode, because it stops all email from being sent.
 * attrs.onconfirm is called when the admin confirms.
 */
export default class CatchModeModal extends Modal {
  className() {
    return 'MailCatcher-catchModal Modal--small';
  }

  title() {
    return t('catch_modal_title');
  }

  content() {
    return (
      <div className="Modal-body">
        <p>{t('catch_modal_text')}</p>
        <p>{t('catch_modal_settings')}</p>
        <p>
          <b>{t('catch_modal_warning')}</b>
        </p>
        <div className="MailCatcher-modalButtons">
          <Button
            className="Button Button--primary"
            icon="fas fa-inbox"
            onclick={() => {
              this.hide();
              this.attrs.onconfirm();
            }}
          >
            {t('catch_modal_confirm')}
          </Button>
        </div>
      </div>
    );
  }
}
