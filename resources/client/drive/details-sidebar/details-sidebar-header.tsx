import {DashboardLayoutContext} from '@common/ui/dashboard-layout/dashboard-layout-context';
import {FileTypeIcon} from '@common/uploads/components/file-type-icon/file-type-icon';
import {IconButton} from '@ui/buttons/icon-button';
import {CloseIcon} from '@ui/icons/material/Close';
import {ReactNode, useContext} from 'react';

interface HeaderProps {
  entryType: string;
  entryName: ReactNode;
}
export function DetailsSidebarHeader({entryType, entryName}: HeaderProps) {
  const {setRightSidenavStatus} = useContext(DashboardLayoutContext);
  return (
    <div className="mb-38 flex gap-16">
      <FileTypeIcon className="h-24 w-24" type={entryType} />
      <div className="mr-auto min-w-0 flex-auto break-words text-base font-medium leading-6 text-main">
        {entryName}
      </div>
      <IconButton
        size="sm"
        iconSize="md"
        className="-mt-6 ml-auto flex-shrink-0"
        onClick={() => {
          setRightSidenavStatus('closed');
        }}
      >
        <CloseIcon />
      </IconButton>
    </div>
  );
}
