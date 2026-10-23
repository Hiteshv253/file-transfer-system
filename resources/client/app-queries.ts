import {DriveEntry, DriveFolder} from '@app/drive/files/drive-entry';
import {DriveApiIndexParams} from '@app/drive/files/queries/use-active-page-entries';
import type {UserFolderPathParams} from '@app/drive/files/queries/use-folder-path';
import type {
  UserFoldersApiParams,
  UserFoldersResponse,
} from '@app/drive/files/queries/use-folders';
import type {FetchStorageSummaryResponse} from '@app/drive/layout/sidebar/storage-summary/storage-summary';
import type {FetchShareableLinkResponse} from '@app/drive/shareable-link/queries/use-entry-shareable-link';
import {
  hasNextPage,
  LengthAwarePaginationResponse,
} from '@common/http/backend-response/pagination-response';
import {queryFactoryHelpers} from '@common/http/queries-file-helpers';
import {infiniteQueryOptions, queryOptions} from '@tanstack/react-query';
import {BootstrapData} from '@ui/bootstrap-data/bootstrap-data';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';

const get = queryFactoryHelpers.get;

export const driveInvalidateKey = ['drive'];

export const appQueries = {
  driveEntries: {
    invalidateKey: [...driveInvalidateKey, 'entries'],
    index: <T = DriveEntry>(params: DriveApiIndexParams) =>
      infiniteQueryOptions({
        queryKey: [...driveInvalidateKey, 'entries', 'index', params],
        queryFn: ({pageParam = 1, signal}) => {
          return get<LengthAwarePaginationResponse<T> & {folder?: DriveFolder}>(
            'drive/file-entries',
            {
              ...params,
              page: pageParam,
            },
            signal,
          );
        },
        initialPageParam: 1,
        getNextPageParam: lastResponse => {
          const currentPage = lastResponse.current_page;
          if (!hasNextPage(lastResponse)) {
            return undefined;
          }
          return currentPage + 1;
        },
      }),
    get: (id: number) =>
      queryOptions<{fileEntry: DriveEntry}>({
        queryKey: [...driveInvalidateKey, 'entries', 'get', id],
        queryFn: () => get(`drive/file-entries/${id}/model`),
      }),
  },
  storageSummary: {
    invalidateKey: [...driveInvalidateKey, 'storage-summary'],
    get: () =>
      queryOptions<FetchStorageSummaryResponse>({
        queryKey: [...driveInvalidateKey, 'storage-summary'],
        queryFn: () => get(`user/space-usage`),
      }),
  },
  folderPath: {
    invalidateKey: [...driveInvalidateKey, 'folders', 'path'],
    get: (hash: string, params: UserFolderPathParams = {}) =>
      queryOptions<{path: DriveFolder[]}>({
        queryKey: ['folder-path', hash, params],
        queryFn: () => get(`folders/${hash}/path`, params),
      }),
  },
  userFolders: {
    invalidateKey: [...driveInvalidateKey, 'folders', 'user'],
    index: (params: UserFoldersApiParams) =>
      queryOptions<UserFoldersResponse>({
        queryKey: ['user-folders', params],
        queryFn: () => get(`users/${params.userId}/folders`, params),
      }),
  },
  shareableLinks: {
    invalidateKey: ['shareable-links'],
    getEntryShareableLink: (entryId: number) =>
      queryOptions<FetchShareableLinkResponse>({
        queryKey: ['shareable-links', 'get-entry', entryId],
        queryFn: () =>
          get(`file-entries/${entryId}/shareable-link`, {
            loader: 'shareableLink',
          }),
      }),
  },
  landingPageData: {
    get: () =>
      queryOptions<Required<Required<BootstrapData>['loaders']>['landingPage']>(
        {
          queryKey: ['landing-page-data'],
          queryFn: () => get('landing-page-data'),
          initialData: () => {
            const data = getBootstrapData().loaders?.landingPage;
            if (data) {
              return data;
            }
          },
        },
      ),
  },
};
