import {AccountCircleIcon} from '@ui/icons/material/AccountCircle';
import {DriveFileMoveIcon} from '@ui/icons/material/DriveFileMove';
import {SettingsIcon} from '@ui/icons/material/Settings';
import {SvgIconProps} from '@ui/icons/svg-icon';
import {ComponentType} from 'react';

export const authDropdownIcons: Record<string, ComponentType<SvgIconProps>> = {
  '/admin/reports': SettingsIcon,
  '/drive': DriveFileMoveIcon,
  '/account-settings': AccountCircleIcon,
};
