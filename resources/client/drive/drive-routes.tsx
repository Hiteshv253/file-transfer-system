import {AuthRoute} from '@common/auth/guards/auth-route';
import {ActiveWorkspaceProvider} from '@common/workspace/active-workspace-id-context';
import {RouteObject} from 'react-router';

export const driveRoutes: RouteObject[] = [
  {
    path: 'drive',
    element: (
      <ActiveWorkspaceProvider>
        <AuthRoute />
      </ActiveWorkspaceProvider>
    ),
    children: [
      {
        index: true,
        lazy: () => import('@app/drive/layout/drive-layout'),
      },
      {
        path: 'folders/:hash',
        lazy: () => import('@app/drive/layout/drive-layout'),
      },
      {
        path: 'shares',
        lazy: () => import('@app/drive/layout/drive-layout'),
      },
      {
        path: 'recent',
        lazy: () => import('@app/drive/layout/drive-layout'),
      },
      {
        path: 'starred',
        lazy: () => import('@app/drive/layout/drive-layout'),
      },
      {
        path: 'trash',
        lazy: () => import('@app/drive/layout/drive-layout'),
      },
      {
        path: 'search',
        lazy: () => import('@app/drive/layout/drive-layout'),
      },
    ],
  },
  {
    path: 'drive/s/:hash',
    lazy: () =>
      import(
        '@app/drive/shareable-link/shareable-link-page/shareable-link-page'
      ),
  },
];
