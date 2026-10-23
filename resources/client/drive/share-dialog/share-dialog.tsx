import {appQueries} from '@app/app-queries';
import {DriveEntry} from '@app/drive/files/drive-entry';
import {LinkSettingsDialog} from '@app/drive/share-dialog/link-panel/link-settings-dialog';
import {ShareableLinkPanel} from '@app/drive/share-dialog/link-panel/shareable-link-panel';
import {SharePanel} from '@app/drive/share-dialog/share-panel';
import {useQuery} from '@tanstack/react-query';
import {Trans} from '@ui/i18n/trans';
import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {AnimatePresence, m} from 'framer-motion';
import {Fragment, useState} from 'react';

export type ShareDialogActivePanel = 'main' | 'linkSettings';

interface ShareDialogProps {
  entry: DriveEntry;
}
export function ShareDialog({entry: initialEntry}: ShareDialogProps) {
  const {
    data: {fileEntry},
  } = useQuery({
    ...appQueries.driveEntries.get(initialEntry.id),
    initialData: {fileEntry: initialEntry},
  });

  const [activePanel, setActivePanel] =
    useState<ShareDialogActivePanel>('main');

  return (
    <Dialog size="lg">
      <AnimatePresence initial={false} mode="wait">
        {activePanel === 'linkSettings' ? (
          <LinkSettingsDialog
            key="one"
            setActivePanel={setActivePanel}
            entry={fileEntry}
          />
        ) : (
          <MainDialog
            key="two"
            setActivePanel={setActivePanel}
            entry={fileEntry}
          />
        )}
      </AnimatePresence>
    </Dialog>
  );
}

interface MainDialogProps {
  setActivePanel: (name: ShareDialogActivePanel) => void;
  entry: DriveEntry;
}
function MainDialog({setActivePanel, entry}: MainDialogProps) {
  return (
    <Fragment>
      <DialogHeader>
        <Trans message="Share ‘:name’" values={{name: entry.name}} />
      </DialogHeader>
      <DialogBody className="relative">
        <m.div
          key="share-content"
          animate={{opacity: 1, y: 0}}
          initial={{opacity: 0, y: 20}}
          exit={{opacity: 0, y: -20}}
          transition={{duration: 0.1}}
        >
          <SharePanel className="mb-30 border-b pb-30" entry={entry} />
          <ShareableLinkPanel setActivePanel={setActivePanel} entry={entry} />
        </m.div>
      </DialogBody>
    </Fragment>
  );
}
