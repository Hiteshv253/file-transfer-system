import {adminRoutes} from '@app/admin/admin-routes';
import {appQueries} from '@app/app-queries';
import {driveRoutes} from '@app/drive/drive-routes';
import {getSettingsPreviewMode} from '@common/admin/settings/preview/use-settings-preview-mode';
import {authRoutes} from '@common/auth/auth-routes';
import {authGuard} from '@common/auth/guards/auth-route';
import {auth} from '@common/auth/use-auth';
import {billingPageRoutes} from '@common/billing/billing-page/billing-page-routes';
import {checkoutRoutes} from '@common/billing/checkout/checkout-routes';
import {RootErrorElement, RootRoute} from '@common/core/common-provider';
import {commonRoutes} from '@common/core/common-routes';
import {queryClient} from '@common/http/query-client';
import {notificationRoutes} from '@common/notifications/notification-routes';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';
import {FullPageLoader} from '@ui/progress/full-page-loader';
import {createBrowserRouter, redirect} from 'react-router';

export const appRouter = createBrowserRouter(
  [
    {
      id: 'root',
      element: <RootRoute />,
      errorElement: <RootErrorElement />,
      hydrateFallbackElement: <FullPageLoader screen />,
      children: [
        {
          path: '/',
          loader: async () => {
            const isLoggedIn = auth.isLoggedIn;

            if (
              !isLoggedIn &&
              getBootstrapData().settings.homepage.type === 'loginPage' &&
              !getSettingsPreviewMode().isInsideSettingsPreview
            ) {
              return redirect('/login');
            }

            if (
              isLoggedIn &&
              !getSettingsPreviewMode().isInsideSettingsPreview
            ) {
              return redirect(getBootstrapData().auth_redirect_uri);
            }
            return await queryClient.ensureQueryData(
              appQueries.landingPageData.get(),
            );
          },
          lazy: () => import('@app/landing-page'),
        },
        ...driveRoutes,
        ...adminRoutes,
        ...authRoutes(),
        ...notificationRoutes,
        ...checkoutRoutes,
        ...billingPageRoutes,
        ...commonRoutes,
        {
          path: 'api-docs',
          loader: () =>
            authGuard({permission: 'api.access', requireLogin: false}),
          lazy: () => import('@common/swagger/swagger-api-docs-page'),
        },
      ],
    },
  ],
  {
    basename: getBootstrapData().settings.html_base_uri,
  },
);
