import {TrashPage} from '@app/drive/drive-page/drive-page';
import {driveState, useDriveStore} from '@app/drive/drive-store';
import {DropTargetMask} from '@app/drive/drop-target-mask';
import {EntryActionList} from '@app/drive/entry-actions/entry-action-list';
import {FileGrid} from '@app/drive/file-view/file-grid/file-grid';
import {FileTable} from '@app/drive/file-view/file-table/file-table';
import {DriveContextMenu} from '@app/drive/files/drive-context-menu';
import {useActivePageEntries} from '@app/drive/files/queries/use-active-page-entries';
import {useDeleteEntries} from '@app/drive/files/queries/use-delete-entries';
import {getSelectedEntries} from '@app/drive/files/use-selected-entries';
import {DriveSortButton} from '@app/drive/layout/sorting/drive-sort-button';
import {PageBreadcrumbs} from '@app/drive/page-breadcrumbs';
import {SearchFilterList} from '@app/drive/search/search-filter-list';
import {useDriveUploadQueue} from '@app/drive/uploading/use-drive-upload-queue';
import {useAuth} from '@common/auth/use-auth';
import {AdHost} from '@common/admin/ads/ad-host';
import {DashboardLayoutContext} from '@common/ui/dashboard-layout/dashboard-layout-context';
import {InfiniteScrollSentinel} from '@common/ui/infinite-scroll/infinite-scroll-sentinel';
import {mergeProps} from '@react-aria/utils';
import {Trans} from '@ui/i18n/trans';
import {IllustratedMessage} from '@ui/images/illustrated-message';
import {SvgImage} from '@ui/images/svg-image';
import {useMouseSelectionBox} from '@ui/interactions/dnd/mouse-selection/use-mouse-selection-box';
import {MixedDraggable} from '@ui/interactions/dnd/use-draggable';
import {useDroppable} from '@ui/interactions/dnd/use-droppable';
import {createEventHandler} from '@ui/utils/dom/create-event-handler';
import {isCtrlKeyPressed} from '@ui/utils/keybinds/is-ctrl-key-pressed';
import clsx from 'clsx';
import {
  KeyboardEventHandler,
  MouseEventHandler,
  ReactNode,
  useContext,
  useRef,
  useState,
} from 'react';
import {useSearchParams} from 'react-router';

interface FileViewProps {
  className?: string;
}
export function FileView({className}: FileViewProps) {
  const [params] = useSearchParams();
  const isSearchingOrFiltering =
    !!params.get('query') || !!params.get('filters');
  const containerRef = useRef<HTMLDivElement>(null);
  const query = useActivePageEntries();
  const {uploadFiles} = useDriveUploadQueue();
  const deleteEntries = useDeleteEntries();
  const activePage = useDriveStore(s => s.activePage);
  const [isDragOver, setIsDragOver] = useState(false);
  const viewMode = useDriveStore(s => s.viewMode);
  const {isMobileMode} = useContext(DashboardLayoutContext);
  const {hasPermission} = useAuth();

  const {containerProps, boxProps} = useMouseSelectionBox({
    containerRef,
    onPointerDown: e => {
      if (!(e.target as HTMLElement).closest('.entry-action-list')) {
        driveState().deselectEntries('all');
      }
    },
  });

  const {droppableProps} = useDroppable({
    id: 'driveRoot',
    ref: containerRef,
    types: ['nativeFile'],
    disabled: !activePage?.canUpload,
    onDragEnter: () => {
      setIsDragOver(true);
    },
    onDragLeave: () => {
      setIsDragOver(false);
    },
    onDrop: async (draggable: MixedDraggable) => {
      if (draggable.type === 'nativeFile') {
        uploadFiles(await draggable.getData());
      }
    },
  });

  if (!activePage) return null;

  let content: ReactNode;
  if (query.isEmpty && (!query.isLoading || query.fetchStatus === 'idle')) {
    const noContentMessage = activePage.noContentMessage(
      isSearchingOrFiltering,
    );
    content = (
      <IllustratedMessage
        className="mt-40"
        image={<SvgImage src={noContentMessage.image} />}
        title={<Trans {...noContentMessage.title} />}
        description={<Trans {...noContentMessage.description} />}
      />
    );
  } else if (!query.isLoading) {
    content =
      viewMode === 'list' ? (
        <FileTable entries={query.items} />
      ) : (
        <FileGrid entries={query.items} />
      );
  }

  const handleContextMenu: MouseEventHandler = e => {
    e.preventDefault();
    e.stopPropagation();
    driveState().deselectEntries('all');
    driveState().setContextMenuData({x: e.clientX, y: e.clientY});
  };

  const handleKeybinds: KeyboardEventHandler = e => {
    if (e.key === 'a' && isCtrlKeyPressed(e)) {
      e.preventDefault();
      e.stopPropagation();
      driveState().selectEntries(query.items.map(entry => entry.id));
    }
    if (e.key === 'Delete') {
      e.preventDefault();
      e.stopPropagation();
      const selectedEntries = getSelectedEntries();
      const canDeleteSelected =
        hasPermission('admin') &&
        selectedEntries.length > 0 &&
        selectedEntries.every(entry => entry.permissions['files.delete']);
      if (canDeleteSelected && !deleteEntries.isPending) {
        if (activePage === TrashPage) {
          driveState().setActiveActionDialog(
            'confirmAndDeleteForever',
            selectedEntries,
          );
        } else {
          deleteEntries.mutate({
            entryIds: [...driveState().selectedEntries],
            deleteForever: true,
          });
          driveState().selectEntries([]);
        }
      }
    }
  };

  return (
    <div
      className={clsx(
        'compact-scrollbar relative flex-auto overflow-y-auto outline-none stable-scrollbar',
        className,
      )}
      tabIndex={-1}
      {...mergeProps(containerProps, droppableProps, {
        onKeyDown: createEventHandler(handleKeybinds),
      })}
      onContextMenu={handleContextMenu}
    >
      <div className="relative flex min-h-full flex-col pt-10">
        {isMobileMode ? (
          <PageBreadcrumbs className="mb-10 px-14" />
        ) : (
          <Toolbar />
        )}
        <SearchFilterList />
        <div className="relative flex-auto px-18 pb-18 md:px-24">
          <AdHost slot="drive" className="mb-24" />
          {content}
          <InfiniteScrollSentinel query={query} />
        </div>
        <div
          {...boxProps}
          className="pointer-events-none absolute left-0 top-0 z-10 hidden border border-primary-light bg-primary-light/20 shadow-md"
        />
        <DriveContextMenu />
        <DropTargetMask isVisible={isDragOver} />
      </div>
    </div>
  );
}

function Toolbar() {
  const activePage = useDriveStore(s => s.activePage);
  return (
    <div className="my-10 flex min-h-42 items-center justify-between gap-40 px-10 text-muted md:px-18">
      <DriveSortButton isDisabled={activePage?.disableSort} />
      <EntryActionList className="text-muted" />
    </div>
  );
}
