import {appQueries} from '@app/app-queries';
import {DriveFolder} from '@app/drive/files/drive-entry';
import {DriveApiIndexParams} from '@app/drive/files/queries/use-active-page-entries';
import {encodeBackendFilters} from '@common/datatable/filters/utils/encode-backend-filters';
import {useActiveWorkspaceId} from '@common/workspace/active-workspace-id-context';
import {keepPreviousData, useInfiniteQuery} from '@tanstack/react-query';
import {useMemo} from 'react';

interface Props {
  query?: string;
}
export function useMoveEntriesDialogFolderSearch({query}: Props) {
  const {workspaceId} = useActiveWorkspaceId();
  const params = useMemo(() => {
    return {
      section: 'search',
      workspaceId,
      query,
      filters: encodeBackendFilters([
        {
          key: 'type',
          value: 'folder',
        },
      ]),
    } as DriveApiIndexParams;
  }, [workspaceId, query]);

  return useInfiniteQuery({
    ...appQueries.driveEntries.index<DriveFolder>(params),
    enabled: !!query,
    placeholderData: keepPreviousData,
  });
}
