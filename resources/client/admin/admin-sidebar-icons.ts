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

export const adminSidebarIcons = {
  '/admin/reports': ChartNoAxesCombinedIcon,
  '/admin/settings/general': SettingsIcon,
  '/admin/subscriptions': PaidIcon,
  '/admin/plans': ReceiptIcon,
  '/admin/roles': ManageAccountsIcon,
  '/admin/custom-pages': ChromeReaderModeIcon,
  '/admin/tags': SellIcon,
  '/admin/files': FileCopyIcon,
  '/admin/localizations': TranslateIcon,
  '/admin/logs': FileClockIcon,
  '/admin/users': PeopleIcon,
};
