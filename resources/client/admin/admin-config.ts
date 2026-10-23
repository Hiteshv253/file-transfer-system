import {SettingsNavItem} from '@common/admin/settings/settings-nav-config';
import {message} from '@ui/i18n/message';
import {ChartNoAxesCombinedIcon} from '@ui/icons/lucide/chart-no-axes-combined';
import {FileClockIcon} from '@ui/icons/lucide/file-clock';
import {ChromeReaderModeIcon} from '@ui/icons/material/ChromeReaderMode';
import {FileCopyIcon} from '@ui/icons/material/FileCopy';
import {ManageAccountsIcon} from '@ui/icons/material/ManageAccounts';
import {PaidIcon} from '@ui/icons/material/Paid';
import {PeopleIcon} from '@ui/icons/material/People';
import {ReceiptIcon} from '@ui/icons/material/Receipt';
import {SellIcon} from '@ui/icons/material/Sell';
import {SettingsIcon} from '@ui/icons/material/Settings';
import {TranslateIcon} from '@ui/icons/material/Translate';

// icons
export const AdminSidebarIcons = {
  '/admin/reports': ChartNoAxesCombinedIcon,
  '/admin/settings': SettingsIcon,
  '/admin/users': PeopleIcon,
  '/admin/roles': ManageAccountsIcon,
  '/admin/subscriptions': PaidIcon,
  '/admin/plans': ReceiptIcon,
  '/admin/custom-pages': ChromeReaderModeIcon,
  '/admin/tags': SellIcon,
  '/admin/files': FileCopyIcon,
  '/admin/localizations': TranslateIcon,
  '/admin/logs': FileClockIcon,
};

// settings nav config
export const AppSettingsNavConfig: SettingsNavItem[] = [
  {label: message('Drive'), to: 'drive', position: 2},
  {label: message('Uploading'), to: 'uploading', position: 2},
  {label: message('Landing page'), to: 'landing-page', position: 2},
  {label: message('Ads'), to: 'ads', position: 9},
];

// docs urls
const base = 'https://support.vebto.com/hc/articles';
export const AdminDocsUrls = {
  manualUpdate: `${base}/21/23/295/updating-to-new-versions#method-2-manual-update`,
  settings: {
    uploading: `${base}/21/79/297/configuring-file-upload`,
    s3: `${base}/21/25/216/storing-files-on-amazon-s3`,
    dropbox: `${base}/21/25/215/storing-files-on-dropbox`,
    backblaze: `${base}/21/25/217/storing-files-on-backblaze`,
    authentication: `${base}/21/25/274/authentication-settings`,
  } as any,
  pages: {
    roles: `${base}/21/47/154/roles-and-permissions`,
    translations: `${base}/21/25/296/language-and-translation`,
  } as any,
};
