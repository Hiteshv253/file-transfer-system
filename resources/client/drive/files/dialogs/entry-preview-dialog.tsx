import {useShareAction} from '@app/drive/entry-actions/use-entry-actions';
import {DriveEntry} from '@app/drive/files/drive-entry';
import {useActivePageEntries} from '@app/drive/files/queries/use-active-page-entries';
import {FilePreviewDialog} from '@common/uploads/components/file-preview/file-preview-dialog';
import {Button} from '@ui/buttons/button';
import {IconButton} from '@ui/buttons/icon-button';
import {Trans} from '@ui/i18n/trans';
import {createElement, Fragment, useState} from 'react';

interface EntryPreviewDialogProps {
  selectedEntry: DriveEntry;
}
export function EntryPreviewDialog({selectedEntry}: EntryPreviewDialogProps) {
  const files = useActivePageEntries().items.filter(
    entry => entry.type !== 'folder',
  );
  const defaultActiveIndex = files.findIndex(
    file => file.id === selectedEntry?.id,
  );
  const [activeIndex, setActiveIndex] = useState(defaultActiveIndex);
  return (
    <FilePreviewDialog
      allowDownload={selectedEntry.permissions['files.download']}
      headerActionsLeft={
        <DriveActions activeIndex={activeIndex} entries={files} />
      }
      activeIndex={activeIndex}
      onActiveIndexChange={setActiveIndex}
      entries={files}
    />
  );
}

interface DriveActionsProps {
  activeIndex: number;
  entries: DriveEntry[];
}
function DriveActions({activeIndex, entries}: DriveActionsProps) {
  const selectedEntry = entries[activeIndex];
  const share = useShareAction([selectedEntry]);
  if (!selectedEntry || !share) return null;

  return (
    <Fragment>
      <IconButton
        className="md:hidden"
        onClick={() => {
          share.execute();
        }}
      >
        {createElement(share.icon)}
      </IconButton>
      <Button
        className="max-md:hidden"
        variant="text"
        startIcon={createElement(share.icon)}
        onClick={() => {
          share.execute();
        }}
      >
        <Trans {...share.label} />
      </Button>
    </Fragment>
  );
}
