import {appQueries} from '@app/app-queries';
import {DriveFolder} from '@app/drive/files/drive-entry';
import {DriveApiIndexParams} from '@app/drive/files/queries/use-active-page-entries';
import {PartialFolder} from '@app/drive/files/utils/can-move-entries-into';
import {encodeBackendFilters} from '@common/datatable/filters/utils/encode-backend-filters';
import {useActiveWorkspaceId} from '@common/workspace/active-workspace-id-context';
import {useInfiniteQuery} from '@tanstack/react-query';
import {useMemo} from 'react';

interface Props {
  selectedFolder: PartialFolder;
  movingSharedFiles: boolean;
}
export function useMoveEntriesDialogFolders({
  selectedFolder,
  movingSharedFiles,
}: Props) {
  const {workspaceId} = useActiveWorkspaceId();
  const params = useMemo(() => {
    const filters = encodeBackendFilters([
      {
        key: 'type',
        value: 'folder',
      },
    ]);
    return {
      section: 'folder',
      folderId:
        selectedFolder.hash == '0' && movingSharedFiles
          ? 'sharedWithMe'
          : selectedFolder.hash,
      workspaceId,
      filters,
    } as DriveApiIndexParams;
  }, [workspaceId, selectedFolder.hash, movingSharedFiles]);

  return useInfiniteQuery(appQueries.driveEntries.index<DriveFolder>(params));
}
