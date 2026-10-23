import {adminCustomPagesRoutes} from '@common/admin/custom-pages/admin-custom-pages-routes';
import {adminFileEntriesRoutes} from '@common/admin/file-entry/admin-file-entries-routes';
import {adminLogsRoutes} from '@common/admin/logging/admin-logs-routes';
import {adminRolesRoutes} from '@common/admin/roles/admin-roles-routes';
import {commonAdminSettingsRoutes} from '@common/admin/settings/common-admin-settings-routes';
import {adminBillingRoutes} from '@common/admin/subscriptions/admin-billing-routes';
import {adminTagsRoutes} from '@common/admin/tags/admin-tags-routes';
import {adminLocalizationsRoutes} from '@common/admin/translations/admin-localizations-routes';
import {adminUsersRoutes} from '@common/admin/users/admin-users-routes';
import {authGuard} from '@common/auth/guards/auth-route';
import {Navigate, RouteObject} from 'react-router';

export const adminRoutes: RouteObject[] = [
  {
    path: 'admin',
    loader: () => authGuard({permission: 'admin.access'}),
    lazy: () => import('@common/admin/admin-layout'),
    children: [
      {
        index: true,
        element: <Navigate to="reports" />,
      },
      {
        path: 'reports',
        lazy: () => import('@common/admin/analytics/admin-report-page'),
      },
      ...Object.values(adminUsersRoutes),
      ...Object.values(adminRolesRoutes),
      ...Object.values(adminBillingRoutes),
      ...Object.values(adminCustomPagesRoutes),
      ...Object.values(adminTagsRoutes),
      ...Object.values(adminLocalizationsRoutes),
      ...Object.values(adminFileEntriesRoutes),
      ...Object.values(adminLogsRoutes),

      commonAdminSettingsRoutes(
        [
          {
            path: 'drive',
            lazy: () => import('@app/admin/settings/drive-settings'),
          },
          {
            path: 'landing-page',
            lazy: () =>
              import(
                '@common/admin/settings/landing-page-settings/landing-page-settings'
              ),
          },
          {
            path: 'ads',
            lazy: () => import('@common/admin/settings/pages/ads-settings'),
          },
        ],
        {
          uploading: {
            lazy: () =>
              import(
                '@common/admin/settings/pages/uploading-settings/uploading-settings'
              ),
          },
          general: {
            lazy: () => import('@app/admin/settings/app-general-settings'),
          },
        },
      ),
    ],
  },
];
