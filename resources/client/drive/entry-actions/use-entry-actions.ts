import {SharesPage, TrashPage} from '@app/drive/drive-page/drive-page';
import {driveState, useDriveStore} from '@app/drive/drive-store';
import {EntryAction} from '@app/drive/entry-actions/entry-action';
import {
  getDirectLink,
  getShareableLink,
} from '@app/drive/entry-actions/get-public-access-link';
import {DriveEntry} from '@app/drive/files/drive-entry';
import {useAddStarToEntries} from '@app/drive/files/queries/use-add-star-to-entries';
import {useDeleteEntries} from '@app/drive/files/queries/use-delete-entries';
import {useDuplicateEntries} from '@app/drive/files/queries/use-duplicate-entries';
import {useRemoveStarFromEntries} from '@app/drive/files/queries/use-remove-star-from-entries';
import {useRestoreEntries} from '@app/drive/files/queries/use-restore-entries';
import {useUnshareEntries} from '@app/drive/share-dialog/queries/use-unshare-entries';
import {useCreateShareableLink} from '@app/drive/shareable-link/queries/create-shareable-link';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useFileEntryUrls} from '@common/uploads/file-entry-urls';
import {message} from '@ui/i18n/message';
import {ContentCopyIcon} from '@ui/icons/material/ContentCopy';
import {DeleteIcon} from '@ui/icons/material/Delete';
import {DriveFileMoveIcon} from '@ui/icons/material/DriveFileMove';
import {DriveFileRenameOutlineIcon} from '@ui/icons/material/DriveFileRenameOutline';
import {FileDownloadIcon} from '@ui/icons/material/FileDownload';
import {LinkIcon} from '@ui/icons/material/Link';
import {PersonAddIcon} from '@ui/icons/material/PersonAdd';
import {RemoveRedEyeIcon} from '@ui/icons/material/RemoveRedEye';
import {RestoreIcon} from '@ui/icons/material/Restore';
import {StarIcon} from '@ui/icons/material/Star';
import {StarOutlineIcon} from '@ui/icons/material/StarOutline';
import {useSettings} from '@ui/settings/use-settings';
import {toast} from '@ui/toast/toast';
import {downloadFileFromUrl} from '@ui/utils/files/download-file-from-url';
import copy from 'copy-to-clipboard';

export function useEntryActions(entries: DriveEntry[]): EntryAction[] {
  const preview = usePreviewAction(entries);
  const share = useShareAction(entries);
  const copyLink = useCopyLinkAction(entries);
  const addStar = useAddToStarredAction(entries);
  const removeStar = useRemoveFromStarred(entries);
  const moveTo = useMoveToAction(entries);
  const rename = useRenameAction(entries);
  const makeCopy = useMakeCopyAction(entries);
  const download = useDownloadEntriesAction(entries);
  const deleteAction = useDeleteEntriesAction(entries);
  const removeSharedEntries = useRemoveSharedEntriesAction(entries);
  const restoreEntries = useRestoreEntriesAction(entries);

  return [
    preview,
    share,
    copyLink,
    addStar,
    removeStar,
    moveTo,
    rename,
    makeCopy,
    download,
    deleteAction,
    removeSharedEntries,
    restoreEntries,
  ].filter(action => !!action) as EntryAction[];
}

export function usePreviewAction(
  entries: DriveEntry[],
): EntryAction | undefined {
  if (!entries.some(e => e.type !== 'folder')) return;
  return {
    label: message('Preview'),
    icon: RemoveRedEyeIcon,
    key: 'preview',
    execute: () => {
      driveState().setActiveActionDialog('preview', entries);
    },
  };
}

export function useShareAction(entries: DriveEntry[]): EntryAction | undefined {
  const activePage = useDriveStore(s => s.activePage);
  if (
    entries.length > 1 ||
    !entries.every(e => e.permissions['files.update']) ||
    activePage === TrashPage
  )
    return;

  return {
    label: message('Share'),
    icon: PersonAddIcon,
    key: 'share',
    execute: () => {
      driveState().setActiveActionDialog('share', entries);
    },
  };
}

function useCopyLinkAction(entries: DriveEntry[]): EntryAction | undefined {
  const activePage = useDriveStore(s => s.activePage);
  const {drive} = useSettings();
  const createLink = useCreateShareableLink();
  if (
    entries.length > 1 ||
    !entries.every(e => e.permissions['files.update']) ||
    activePage === TrashPage
  ) {
    return;
  }
  return {
    label: message('Copy link'),
    icon: LinkIcon,
    key: 'copyLink',
    execute: async () => {
      const isUsingDirectLinks =
        drive?.copy_link_default === 'direct' && entries[0].type !== 'folder';
      const r = await createLink.mutateAsync({
        entryId: entries[0].id,
        enableDirectLinks: isUsingDirectLinks,
      });
      if (r.link) {
        const publicLink = isUsingDirectLinks
          ? getDirectLink(r.link, entries[0])
          : getShareableLink(r.link);
        copy(publicLink);
        toast(message('Link copied'), {
          action: {
            label: message('Manage access'),
            onExecute: () => {
              driveState().setActiveActionDialog('share', entries);
            },
          },
        });
      } else {
        toast.danger(message('Could not create link'));
      }
    },
  };
}

function useAddToStarredAction(entries: DriveEntry[]): EntryAction | undefined {
  const activePage = useDriveStore(s => s.activePage);
  const starEntries = useAddStarToEntries();
  if (
    entries.every(e => e.tags?.find(tag => tag.name === 'starred')) ||
    !entries.every(e => e.permissions['files.update']) ||
    activePage === TrashPage
  ) {
    return;
  }
  return {
    label: message('Add to starred'),
    icon: StarIcon,
    key: 'addToStarred',
    execute: () => {
      starEntries.mutate({entryIds: entries.map(e => e.id)});
      driveState().selectEntries([]);
    },
  };
}

function useRemoveFromStarred(entries: DriveEntry[]): EntryAction | undefined {
  const activePage = useDriveStore(s => s.activePage);
  const removeStar = useRemoveStarFromEntries();
  if (
    !entries.every(e => e.tags?.find(tag => tag.name === 'starred')) ||
    activePage === TrashPage
  )
    return;
  return {
    label: message('Remove from starred'),
    icon: StarOutlineIcon,
    key: 'removeFromStarred',
    execute: () => {
      removeStar.mutate({entryIds: entries.map(e => e.id)});
      driveState().selectEntries([]);
    },
  };
}

function useMoveToAction(entries: DriveEntry[]): EntryAction | undefined {
  const activePage = useDriveStore(s => s.activePage);
  if (
    !entries.every(e => e.permissions['files.update']) ||
    activePage === SharesPage ||
    activePage === TrashPage
  ) {
    return;
  }

  return {
    label: message('Move to'),
    icon: DriveFileMoveIcon,
    key: 'moveTo',
    execute: () => {
      driveState().setActiveActionDialog('moveTo', entries);
    },
  };
}

function useRenameAction(entries: DriveEntry[]): EntryAction | undefined {
  const activePage = useDriveStore(s => s.activePage);
  if (
    entries.length > 1 ||
    !entries.every(e => e.permissions['files.update']) ||
    activePage === TrashPage
  )
    return;
  return {
    label: message('Rename'),
    icon: DriveFileRenameOutlineIcon,
    key: 'rename',
    execute: () => {
      driveState().setActiveActionDialog('rename', entries);
    },
  };
}

function useMakeCopyAction(entries: DriveEntry[]): EntryAction | undefined {
  const activePage = useDriveStore(s => s.activePage);
  const duplicateEntries = useDuplicateEntries();
  if (
    entries.length > 1 ||
    !entries.every(e => e.permissions['files.update']) ||
    activePage === TrashPage
  ) {
    return;
  }
  return {
    label: message('Make a copy'),
    icon: ContentCopyIcon,
    key: 'makeCopy',
    execute: () => {
      duplicateEntries.mutate({
        entryIds: entries.map(e => e.id),
        destinationId: activePage?.folder?.id,
      });
      driveState().selectEntries([]);
    },
  };
}

function useDownloadEntriesAction(
  entries: DriveEntry[],
): EntryAction | undefined {
  const {downloadUrl} = useFileEntryUrls(entries[0], {
    downloadHashes: entries.map(e => e.hash),
  });
  if (!entries.every(e => e.permissions['files.download'])) return;
  return {
    label: message('Download'),
    icon: FileDownloadIcon,
    key: 'download',
    execute: () => {
      if (downloadUrl) {
        downloadFileFromUrl(downloadUrl);
      }
      driveState().selectEntries([]);
    },
  };
}

export function useDeleteEntriesAction(
  entries: DriveEntry[],
): EntryAction | undefined {
  const deleteEntries = useDeleteEntries();
  const activePage = useDriveStore(s => s.activePage);
  if (
    activePage === SharesPage ||
    !entries.every(e => e.permissions['files.delete'])
  )
    return;
  return {
    label: message('Delete forever'),
    icon: DeleteIcon,
    key: 'delete',
    execute: () => {
      if (activePage === TrashPage) {
        driveState().setActiveActionDialog('confirmAndDeleteForever', entries);
      } else {
        deleteEntries.mutate({
          entryIds: entries.map(e => e.id),
          deleteForever: true,
        });
        driveState().selectEntries([]);
      }
    },
  };
}

export function useRestoreEntriesAction(
  entries: DriveEntry[],
): EntryAction | undefined {
  const restoreEntries = useRestoreEntries();
  const activePage = useDriveStore(s => s.activePage);
  if (
    activePage !== TrashPage ||
    !entries.every(e => e.permissions['files.delete'])
  )
    return;
  return {
    label: message('Restore'),
    icon: RestoreIcon,
    key: 'restore',
    execute: () => {
      restoreEntries.mutate({
        entryIds: entries.map(e => e.id),
      });
      driveState().selectEntries([]);
    },
  };
}

export function useRemoveSharedEntriesAction(
  entries: DriveEntry[],
): EntryAction | undefined {
  const unshareEntries = useUnshareEntries();
  const activePage = useDriveStore(s => s.activePage);
  if (activePage !== SharesPage) return;
  return {
    label: message('Remove'),
    icon: DeleteIcon,
    key: 'removeSharedEntry',
    execute: () => {
      unshareEntries.mutate(
        {entryIds: entries.map(e => e.id), userId: 'me'},
        {
          onSuccess: (r, p) => {
            toast(
              message('Removed [one 1 item|other {count} items]', {
                values: {count: p.entryIds.length},
              }),
            );
          },
          onError: err =>
            showHttpErrorToast(err, message('Could not remove items')),
        },
      );
      driveState().selectEntries([]);
    },
  };
}
