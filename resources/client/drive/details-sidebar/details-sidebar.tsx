import {DetailsSidebarHeader} from '@app/drive/details-sidebar/details-sidebar-header';
import {DetailsSidebarProperties} from '@app/drive/details-sidebar/details-sidebar-properties';
import {Trans} from '@ui/i18n/trans';
import {IllustratedMessage} from '@ui/images/illustrated-message';
import {SvgImage} from '@ui/images/svg-image';
import clsx from 'clsx';
import {Fragment} from 'react';
import {useSelectedEntries} from '@app/drive/files/use-selected-entries';
import detailedExamination from '@app/drive/details-sidebar/detailed-examination.svg';

interface DetailsSidebarProps {
  className?: string;
}
export function DetailsSidebar({className}: DetailsSidebarProps) {
  const selectedEntry = useSelectedEntries()[0];
  return (
    <div
      className={clsx(
        className,
        'dashboard-rounded-panel h-full overflow-y-auto border-divider-lighter p-18 text-sm text-muted lg:ml-8',
      )}
    >
      {selectedEntry ? (
        <DetailsSidebarProperties entry={selectedEntry} />
      ) : (
        <NothingSelected />
      )}
    </div>
  );
}

function NothingSelected() {
  return (
    <Fragment>
      <DetailsSidebarHeader
        entryType="folder"
        entryName={<Trans message="All files" />}
      />
      <IllustratedMessage
        image={<SvgImage src={detailedExamination} />}
        description={<Trans message="Select file or folder to see details" />}
      />
    </Fragment>
  );
}
