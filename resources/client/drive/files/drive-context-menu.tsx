import {Trans} from '@ui/i18n/trans';
import {ContextMenu} from '@ui/menu/context-menu';
import {MenuItem} from '@ui/menu/menu-trigger';
import {createElement} from 'react';
import {DrivePage, RootFolderPage} from '@app/drive/drive-page/drive-page';
import {driveState, useDriveStore} from '@app/drive/drive-store';
import {EntryAction} from '@app/drive/entry-actions/entry-action';
import {useDrivePageActions} from '@app/drive/entry-actions/use-drive-page-actions';
import {useEntryActions} from '@app/drive/entry-actions/use-entry-actions';
import {DriveEntry} from '@app/drive/files/drive-entry';
import {useSelectedEntries} from '@app/drive/files/use-selected-entries';

export function DriveContextMenu() {
  const selectedEntries = useSelectedEntries();
  const activePage = useDriveStore(s => s.activePage);
  const data = useDriveStore(s => s.contextMenuData);
  const entries = data?.entry ? [data.entry] : selectedEntries;

  // right-clicked root folder
  if (data?.entry?.id === 0) {
    return <PageContextMenu position={data} page={RootFolderPage} />;
  }

  if (data && entries.length) {
    return <EntriesContextMenu entries={entries} position={data} />;
  }

  if (data && activePage) {
    return <PageContextMenu position={data} page={activePage} />;
  }

  return null;
}

interface EntriesContextMenuProps {
  position: {x: number; y: number};
  entries: DriveEntry[];
}
function EntriesContextMenu({entries, position}: EntriesContextMenuProps) {
  const actions = useEntryActions(entries);
  return <BaseContextMenu position={position} actions={actions} />;
}

interface PageContextMenuProps {
  position: {x: number; y: number};
  page: DrivePage;
}
function PageContextMenu({page, position}: PageContextMenuProps) {
  const actions = useDrivePageActions(page);
  return <BaseContextMenu position={position} actions={actions} />;
}

interface BaseContextMenuProps {
  position: {x: number; y: number};
  actions: EntryAction[];
}
function BaseContextMenu({position, actions}: BaseContextMenuProps) {
  return (
    <ContextMenu
      floatingMinWidth="min-w-224"
      position={position}
      onOpenChange={isOpen => {
        if (!isOpen) {
          driveState().setContextMenuData(null);
        }
      }}
    >
      {actions.map(action => (
        <MenuItem
          value={action.key}
          key={action.key}
          onSelected={action.execute}
          startIcon={createElement(action.icon)}
        >
          <Trans {...action.label} />
        </MenuItem>
      ))}
    </ContextMenu>
  );
}
