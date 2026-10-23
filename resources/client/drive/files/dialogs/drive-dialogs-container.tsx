import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {ReactElement} from 'react';
import {ActiveActionDialog, driveState, useDriveStore} from '@app/drive/drive-store';
import {ShareDialog} from '@app/drive/share-dialog/share-dialog';
import {BlockTrashFolderViewDialog} from '@app/drive/files/dialogs/block-trash-folder-view-dialog';
import {DeleteEntriesForeverDialog} from '@app/drive/files/dialogs/delete-entries-forever-dialog';
import {EntryPreviewDialog} from '@app/drive/files/dialogs/entry-preview-dialog';
import {MoveEntriesDialog} from '@app/drive/files/dialogs/move-entries-dialog/move-entries-dialog';
import {NewFolderDialog} from '@app/drive/files/dialogs/new-folder-dialog';
import {RenameEntryDialog} from '@app/drive/files/dialogs/rename-entry-dialog';

export function DriveDialogsContainer() {
  const activeDialog = useDriveStore(s => s.activeActionDialog);
  const dialog = getDialog(activeDialog);

  return (
    <DialogTrigger
      type="modal"
      isOpen={!!dialog}
      onClose={() => {
        driveState().setActiveActionDialog(null);
      }}
    >
      {dialog}
    </DialogTrigger>
  );
}

function getDialog(dialog?: ActiveActionDialog | null): ReactElement | null {
  switch (dialog?.name) {
    case 'rename':
      return <RenameEntryDialog entries={dialog.entries} />;
    case 'newFolder':
      return <NewFolderDialog parentId={dialog.entries[0]?.id} />;
    case 'preview':
      return <EntryPreviewDialog selectedEntry={dialog.entries[0]} />;
    case 'share':
      return <ShareDialog entry={dialog.entries[0]} />;
    case 'moveTo':
      return <MoveEntriesDialog entries={dialog.entries} />;
    case 'confirmAndDeleteForever':
      return <DeleteEntriesForeverDialog entries={dialog.entries} />;
    case 'trashFolderBlock':
      return <BlockTrashFolderViewDialog entries={dialog.entries} />;
    default:
      return null;
  }
}
