import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import MailCatcherPage from './components/MailCatcherPage';

export const extend = [new Extend.Admin().page(MailCatcherPage)];

app.initializers.add('mrcaspan-flarum2-mail-catcher', () => {});
