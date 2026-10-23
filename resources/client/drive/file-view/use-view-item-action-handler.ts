import {useCallback} from 'react';
import {getPathForFolder, TrashPage} from '@app/drive/drive-page/drive-page';
import {driveState} from '@app/drive/drive-store';
import {DriveEntry} from '@app/drive/files/drive-entry';
import {getSelectedEntries} from '@app/drive/files/use-selected-entries';
import {useNavigate} from '@common/ui/navigation/use-navigate';

export function useViewItemActionHandler() {
  const navigate = useNavigate();

  const performViewItemAction = useCallback(
    (entry: DriveEntry) => {
      if (entry && entry.type === 'folder') {
        if (driveState().activePage === TrashPage) {
          driveState().setActiveActionDialog('trashFolderBlock', [entry]);
        } else {
          navigate(getPathForFolder(entry.hash));
        }
      } else {
        const selectedEntries = getSelectedEntries();
        driveState().setActiveActionDialog(
          'preview',
          selectedEntries.length ? selectedEntries : [entry],
        );
      }
    },
    [navigate],
  );

  return {performViewItemAction};
}
