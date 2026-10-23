import { useDroppable } from '@ui/interactions/dnd/use-droppable';
import { RefObject, useState } from 'react';
import { driveState } from '@app/drive/drive-store';
import { DriveFolder } from '@app/drive/files/drive-entry';
import {
  folderAcceptsDrop,
  useFolderDropAction,
} from '@app/drive/files/use-folder-drop-action';
import { makeFolderTreeDragId } from '@app/drive/layout/sidebar/folder-tree-drag-id';

interface Props {
  folder: DriveFolder;
  ref: RefObject<HTMLDivElement | null>;
}
export function useSidebarTreeDropTarget({folder, ref}: Props) {
  const [isDragOver, setIsDragOver] = useState(false);
  const {onDrop} = useFolderDropAction(folder);

  const dropProps = useDroppable({
    id: makeFolderTreeDragId(folder),
    ref,
    types: ['fileEntry', 'nativeFile'],
    acceptsDrop: draggable => folderAcceptsDrop(draggable, folder),
    onDragEnter: draggable => {
      if (folderAcceptsDrop(draggable, folder)) {
        setIsDragOver(true);
      }
    },
    onDragLeave: () => {
      setIsDragOver(false);
    },
    onDropActivate: () => {
      if (!driveState().sidebarExpandedKeys.includes(folder.id)) {
        driveState().setSidebarExpandedKeys([
          ...driveState().sidebarExpandedKeys,
          folder.id,
        ]);
      }
    },
    onDrop,
  });

  return {...dropProps, isDragOver};
}
