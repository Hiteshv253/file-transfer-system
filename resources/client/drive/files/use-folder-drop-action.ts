import {driveState} from '@app/drive/drive-store';
import {DriveEntry, DriveFolder} from '@app/drive/files/drive-entry';
import {
  ConnectedDraggable,
  MixedDraggable,
} from '@ui/interactions/dnd/use-draggable';
import {NativeFileDraggable} from '@ui/interactions/dnd/use-droppable';
import {useDriveUploadQueue} from '@app/drive/uploading/use-drive-upload-queue';
import {useMoveEntries} from '@app/drive/files/queries/use-move-entries';
import {canMoveEntriesInto} from '@app/drive/files/utils/can-move-entries-into';

export function useFolderDropAction(folder: DriveEntry) {
  const moveEntries = useMoveEntries();
  const {uploadFiles} = useDriveUploadQueue();

  const onDrop = async (target: ConnectedDraggable | NativeFileDraggable) => {
    if (folder.type !== 'folder') return;
    if (target.type === 'nativeFile') {
      uploadFiles(await target.getData(), {
        parentId: folder.id,
      });
    } else if (target.type === 'fileEntry') {
      const entries = target.getData() as DriveEntry[];
      if (
        entries?.length &&
        canMoveEntriesInto(entries, folder as DriveFolder)
      ) {
        moveEntries.mutate({
          destinationId: folder.id,
          entryIds: entries.map(e => e.id),
        });
        driveState().deselectEntries('all');
      }
    }
  };

  return {onDrop};
}

export function folderAcceptsDrop(
  target: MixedDraggable,
  destination: DriveFolder,
) {
  if (target.type === 'fileEntry') {
    const entries = target.getData() as DriveEntry[];
    return canMoveEntriesInto(entries, destination);
  }
  return true;
}
