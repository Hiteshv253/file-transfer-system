import {SiteConfigContextValue} from '@common/core/settings/site-config-context';
import {CommonUploadType} from '@common/uploads/common-upload-type';
import {message} from '@ui/i18n/message';
import driveSrc from './admin/verts/drive-top.webp';
import landingTopSrc from './admin/verts/landing-top.webp';
import filePreviewSrc from './admin/verts/shareable-link.webp';
import {FileEntrySharedNotificationRenderer} from './drive/notifications/file-entry-shared-notification-renderer';

const fileEntrySharedNotif = 'App\\Notifications\\FileEntrySharedNotif';

export const SiteConfig: Partial<SiteConfigContextValue> = {
  notifications: {
    renderMap: {
      [fileEntrySharedNotif]: FileEntrySharedNotificationRenderer,
    },
  },
  tags: {
    types: [{name: 'label', system: true}],
  },
  roles: {
    types: [
      {type: 'users', label: message('Users'), permission_type: 'users'},
      {
        type: 'workspace',
        label: message('Workspace'),
        permission_type: 'workspace',
      },
    ],
  },
  admin: {
    ads: [
      {
        slot: 'ads.landing-top',
        description: message(
          'This ad will appear at the top of the landing page.',
        ),
        image: landingTopSrc,
      },
      {
        slot: 'ads.drive',
        description: message('This ad will appear on user drive page.'),
        image: driveSrc,
      },
      {
        slot: 'ads.file-preview',
        description: message(
          'This ad will appear on shared file preview page.',
        ),
        image: filePreviewSrc,
      },
    ],
  },
};

export const UploadType = {
  ...CommonUploadType,
  bedrive: 'bedrive',
} as const;
