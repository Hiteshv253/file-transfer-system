import {appQueries} from '@app/app-queries';
import {makeFolderPage, SearchPage} from '@app/drive/drive-page/drive-page';
import {driveState, useDriveStore} from '@app/drive/drive-store';
import {DriveEntry, DriveFolder} from '@app/drive/files/drive-entry';
import {
  SortColumn,
  SortDirection,
} from '@app/drive/layout/sorting/available-sorts';
import {LengthAwarePaginationResponse} from '@common/http/backend-response/pagination-response';
import {useActiveWorkspaceId} from '@common/workspace/active-workspace-id-context';
import {InfiniteData, useInfiniteQuery} from '@tanstack/react-query';
import {shallowEqual} from '@ui/utils/shallow-equal';
import {useEffect, useMemo} from 'react';
import {useSearchParams} from 'react-router';

export interface DriveApiIndexParams {
  orderBy?: SortColumn;
  orderDir?: SortDirection;
  folderId?: string | number | null;
  query?: string;
  filters?: string;
  deletedOnly?: boolean;
  starredOnly?: boolean;
  sharedOnly?: boolean;
  perPage?: number;
  page?: number;
  recentOnly?: boolean;
  workspaceId?: number | null;
  section?: string;
}

export interface EntriesPaginationResponse
  extends LengthAwarePaginationResponse<DriveEntry> {
  folder?: DriveFolder;
}

const setActiveFolder = (response: InfiniteData<EntriesPaginationResponse>) => {
  const firstPage = response.pages[0];
  const newFolder = firstPage.folder;
  const currentPage = driveState().activePage;

  if (
    newFolder &&
    currentPage &&
    currentPage.uniqueId === newFolder.hash &&
    // only update page if once to set the folder or if permissions change, to keep page reference as stable as possible
    (!currentPage.folder ||
      !shallowEqual(newFolder.permissions, currentPage.folder?.permissions))
  ) {
    driveState().setActivePage(makeFolderPage(newFolder));
  }
  return response;
};

export function useActivePageEntries() {
  const page = useDriveStore(s => s.activePage);
  const sortDescriptor = useDriveStore(s => s.sortDescriptor);
  const [searchParams] = useSearchParams();
  const {workspaceId} = useActiveWorkspaceId();
  const params: DriveApiIndexParams = {
    section: page?.name,
    ...page?.queryParams,
    ...Object.fromEntries(searchParams),
    folderId: page?.isFolderPage ? page.uniqueId : null,
    workspaceId,
    ...sortDescriptor,
  };

  // if we have no search query, there's no need to call the API, show no results message instead
  const isDisabledInSearch =
    page === SearchPage && !params.query && !params.filters;

  const query = useInfiniteQuery({
    ...appQueries.driveEntries.index(params),
    enabled: page != null && !isDisabledInSearch,
  });

  // need to do this in effect, to avoid react errors about
  // multiple components re-rendering at the same time
  useEffect(() => {
    if (query.data?.pages[0].folder) {
      setActiveFolder(query.data);
    }
  }, [query.data]);

  const items = useMemo(() => {
    return query.data?.pages.flatMap(p => p.data) || [];
  }, [query.data?.pages]);

  return {
    ...query,
    items,
    isEmpty: !items.length,
  };
}
