import {useDeleteEntries} from '@app/drive/files/queries/use-delete-entries';
import {driveSidebarIcons} from '@app/drive/layout/sidebar/drive-sidebar-icons';
import {FolderTree} from '@app/drive/layout/sidebar/folder-tree';
import {useAuth} from '@common/auth/use-auth';
import {CustomMenu, CustomMenuItem} from '@common/menus/custom-menu';
import {MenuItemConfig} from '@common/menus/menu-config';
import {useDroppable} from '@common/ui/library/interactions/dnd/use-droppable';
import {FileEntry} from '@common/uploads/file-entry';
import clsx from 'clsx';
import {ComponentPropsWithoutRef, forwardRef, useRef, useState} from 'react';
import {MenuPositions} from '@app/drive/menu-positions';

export function SidebarMenu() {
  return (
    <div className="mt-26 px-12 text-muted">
      <FolderTree />
      <CustomMenu
        defaultIcons={driveSidebarIcons}
        menu={MenuPositions.DriveSidebar}
        orientation="vertical"
        gap="gap-0"
      >
        {item => {
          if (item.action === '/drive/trash') {
            return <TrashMenuItem key={item.id} item={item} />;
          }
          return <MenuItem key={item.id} item={item} />;
        }}
      </CustomMenu>
    </div>
  );
}

interface MenuItemProps extends ComponentPropsWithoutRef<'a'> {
  item: MenuItemConfig;
  className?: string;
}
export const MenuItem = forwardRef<HTMLAnchorElement, MenuItemProps>(
  ({item, className, ...domProps}, ref) => {
    return (
      <CustomMenuItem
        defaultIcons={driveSidebarIcons}
        className={({isActive}) =>
          clsx(
            className,
            'my-4 h-40 w-full rounded-button px-24',
            isActive
              ? 'cursor-default bg-primary/selected font-bold text-primary'
              : 'hover:bg-hover',
          )
        }
        item={item}
        ref={ref}
        {...domProps}
      />
    );
  },
);

interface TrashMenuItemProps {
  item: MenuItemConfig;
}
function TrashMenuItem({item}: TrashMenuItemProps) {
  const deleteEntries = useDeleteEntries();
  const {hasPermission} = useAuth();
  const [isDragOver, setIsDragOver] = useState(false);
  const ref = useRef<HTMLAnchorElement>(null);
  const canDelete = hasPermission('admin');

  const {droppableProps} = useDroppable({
    id: 'trash',
    types: ['fileEntry'],
    ref,
    disabled: !canDelete,
    onDragEnter: () => {
      setIsDragOver(true);
    },
    onDragLeave: () => {
      setIsDragOver(false);
    },
    onDrop: draggable => {
      const entryIds = (draggable.getData() as FileEntry[]).map(e => e.id);
      deleteEntries.mutate({entryIds, deleteForever: true});
    },
  });
  return (
    <MenuItem
      className={clsx(isDragOver && canDelete && 'bg-primary/selected')}
      ref={ref}
      {...droppableProps}
      item={item}
    />
  );
}
