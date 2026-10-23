import {DashboardContentHeader} from '@common/ui/dashboard-layout/dashboard-content-header';
import {DashboardLayoutContext} from '@common/ui/dashboard-layout/dashboard-layout-context';
import {ButtonGroup} from '@ui/buttons/button-group';
import {IconButton} from '@ui/buttons/icon-button';
import {Trans} from '@ui/i18n/trans';
import {LayoutGridIcon} from '@ui/icons/lucide/layout-grid';
import {LayoutListIcon} from '@ui/icons/lucide/layout-list';
import {InfoIcon} from '@ui/icons/material/Info';
import {Tooltip} from '@ui/tooltip/tooltip';
import {useContext} from 'react';
import {driveState, useDriveStore} from '@app/drive/drive-store';
import {PageBreadcrumbs} from '@app/drive/page-breadcrumbs';
import {DriveSortButton} from '@app/drive/layout/sorting/drive-sort-button';

export function DriveContentHeader() {
  const {isMobileMode} = useContext(DashboardLayoutContext);
  const activePage = useDriveStore(s => s.activePage);
  return (
    <DashboardContentHeader className="flex h-60 flex-shrink-0 items-center gap-20 border-b border-divider-lighter px-8 py-4 md:px-26">
      {isMobileMode ? (
        <DriveSortButton isDisabled={activePage?.disableSort} />
      ) : (
        <PageBreadcrumbs />
      )}
      <div className="ml-auto flex flex-shrink-0 items-center gap-6">
        <ToggleViewModeButton />
        <ToggleDetailsButton />
      </div>
    </DashboardContentHeader>
  );
}

function ToggleViewModeButton() {
  const viewMode = useDriveStore(s => s.viewMode);
  const tooltip =
    viewMode === 'grid' ? (
      <Trans message="List view" />
    ) : (
      <Trans message="Grid view" />
    );

  return (
    <ButtonGroup
      size="xs"
      variant="outline"
      radius="rounded-full"
      value={viewMode}
      onChange={value => {
        driveState().setViewMode(value);
      }}
    >
      <IconButton value="list" className="min-w-54">
        <LayoutListIcon />
      </IconButton>
      <IconButton value="grid" className="min-w-54">
        <LayoutGridIcon />
      </IconButton>
    </ButtonGroup>
  );
}

function ToggleDetailsButton() {
  const {rightSidenavStatus: status, setRightSidenavStatus} = useContext(
    DashboardLayoutContext,
  );
  const tooltip = status ? (
    <Trans message="Hide details" />
  ) : (
    <Trans message="Show details" />
  );
  return (
    <Tooltip label={tooltip}>
      <IconButton
        radius="rounded-full"
        size="sm"
        color={status === 'open' ? 'primary' : null}
        onClick={() => {
          setRightSidenavStatus(status === 'open' ? 'closed' : 'open');
        }}
      >
        <InfoIcon />
      </IconButton>
    </Tooltip>
  );
}
