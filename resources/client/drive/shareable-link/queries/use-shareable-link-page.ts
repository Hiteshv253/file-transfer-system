import {appQueries} from '@app/app-queries';
import type {DriveEntry} from '@app/drive/files/drive-entry';
import {
  linkPageState,
  useLinkPageStore,
} from '@app/drive/shareable-link/shareable-link-page/link-page-store';
import {LengthAwarePaginationResponse} from '@common/http/backend-response/pagination-response';
import {apiClient} from '@common/http/query-client';
import {SortDescriptor} from '@common/ui/tables/types/sort-descriptor';
import {
  keepPreviousData,
  partialMatchKey,
  useInfiniteQuery,
} from '@tanstack/react-query';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';
import {useParams} from 'react-router';
import type {FetchShareableLinkResponse} from './use-entry-shareable-link';

export interface FetchShareableLinkPageResponse
  extends FetchShareableLinkResponse {
  folderChildren: LengthAwarePaginationResponse<DriveEntry>;
  passwordInvalid: boolean;
}

export function useShareableLinkPage() {
  const {hash} = useParams();
  const sortDescriptor = useLinkPageStore(s => s.activeSort);
  //const order = `${orderBy}:${orderDir}`;
  const isPasswordProtected = useLinkPageStore(s => s.isPasswordProtected);
  const password = useLinkPageStore(s => s.password);

  const queryKey = buildQueryKey(hash!, sortDescriptor, password);

  const query = useInfiniteQuery({
    queryKey,
    queryFn: async ({pageParam = 1}) => {
      const response = await apiClient
        .get<FetchShareableLinkPageResponse>(`shareable-link-page/${hash}`, {
          params: {
            loader: 'shareableLinkPage',
            page: pageParam,
            password,
            ...sortDescriptor,
          },
        })
        .then(response => response.data);

      if (response.passwordInvalid) {
        linkPageState().setIsPasswordProtected(true);
      }
      return response;
    },
    initialData: () => {
      const data = getBootstrapData().loaders?.shareableLinkPage;
      if (!data?.link) return undefined;

      const queryKeyMatches = partialMatchKey(
        queryKey,
        buildQueryKey(
          data.link.hash,
          {
            orderBy: 'updated_at',
            orderDir: 'desc',
          },
          password,
        ),
      );

      if (queryKeyMatches) {
        if (data.passwordInvalid) {
          linkPageState().setIsPasswordProtected(true);
        }
        return {
          pageParams: [undefined, 1],
          pages: [data],
        };
      }
    },
    initialPageParam: 1,
    getNextPageParam: lastResponse => {
      if (!lastResponse.folderChildren) return undefined;
      const currentPage = lastResponse.folderChildren.current_page;
      const lastPage = lastResponse.folderChildren.last_page;
      if (currentPage >= lastPage) {
        return undefined;
      }
      return currentPage + 1;
    },
    // disable query if link is password protected and correct
    // password was not entered yet, to prevent unnecessary requests
    enabled: (!!hash && !isPasswordProtected) || password != null,
    placeholderData: keepPreviousData,
  });

  return {
    ...query,
    link: query.data?.pages[0].link,
    entries: query.data?.pages.flatMap(p => p.folderChildren?.data),
  };
}

const buildQueryKey = (
  hash: string,
  order: SortDescriptor,
  password: string | null,
) => [
  appQueries.shareableLinks.invalidateKey,
  'page',
  hash,
  `${order.orderBy}:${order.orderDir}`,
  password,
];
