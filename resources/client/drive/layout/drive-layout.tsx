import {StaticPageTitle} from '@common/seo/static-page-title';
import {DashboardContent} from '@common/ui/dashboard-layout/dashboard-content';
import {DashboardLayout} from '@common/ui/dashboard-layout/dashboard-layout';
import {DashboardLayoutContext} from '@common/ui/dashboard-layout/dashboard-layout-context';
import {DashboardNavbar} from '@common/ui/dashboard-layout/dashboard-navbar';
import {DashboardSidenav} from '@common/ui/dashboard-layout/dashboard-sidenav';
import {FileEntryUrlsContext} from '@common/uploads/file-entry-urls';
import {FileUploadProvider} from '@common/uploads/uploader/file-upload-provider';
import {FileUploadStoreOptions} from '@common/uploads/uploader/file-upload-store';
import {getActiveWorkspaceId} from '@common/workspace/active-workspace-id';
import {useActiveWorkspaceId} from '@common/workspace/active-workspace-id-context';
import {IconButton} from '@ui/buttons/icon-button';
import {Trans} from '@ui/i18n/trans';
import {CloseIcon} from '@ui/icons/material/Close';
import {SearchIcon} from '@ui/icons/material/Search';
import {Fragment, useContext, useEffect, useMemo} from 'react';
import {Link, useLocation, useParams} from 'react-router';
import {DetailsSidebar} from '@app/drive/details-sidebar/details-sidebar';
import {
  DRIVE_PAGES,
  makePartialFolderPage,
  SearchPage,
} from '@app/drive/drive-page/drive-page';
import {driveState, useDriveStore} from '@app/drive/drive-store';
import {EntryActionList} from '@app/drive/entry-actions/entry-action-list';
import {EntryDragPreview} from '@app/drive/file-view/entry-drag-preview';
import {FileView} from '@app/drive/file-view/file-view';
import {DriveDialogsContainer} from '@app/drive/files/dialogs/drive-dialogs-container';
import {NavbarSearch} from '@app/drive/search/navbar-search';
import {UploadQueue} from '@app/drive/uploading/upload-queue';
import {CreateNewButton} from '@app/drive/layout/create-new-button';
import {DriveContentHeader} from '@app/drive/layout/drive-content-header';
import {Sidebar} from '@app/drive/layout/sidebar/sidebar';

const uploadStoreOptions: FileUploadStoreOptions = {
  modifyUploadedFile: uploadedFile => {
    const workspaceId = getActiveWorkspaceId();
    uploadedFile.fingerprint = `${uploadedFile.fingerprint}-w-${workspaceId}`;
    return uploadedFile;
  },
};

export function Component() {
  const {pathname} = useLocation();
  const {hash} = useParams();
  const {workspaceId} = useActiveWorkspaceId();
  const activePage = useDriveStore(s => s.activePage);

  useEffect(() => {
    driveState().setActivePage(
      DRIVE_PAGES.find(p => p.path === pathname) ||
        makePartialFolderPage(hash!),
    );
  }, [pathname, hash]);

  const urlsContextValue = useMemo(() => {
    return {workspaceId};
  }, [workspaceId]);

  useEffect(() => {
    return () => {
      driveState().reset();
    };
  }, []);

  return (
    <Fragment>
      {activePage?.label && (
        <StaticPageTitle>
          <Trans
            message={
              typeof activePage.label === 'string'
                ? activePage.label
                : activePage.label.message
            }
          />
        </StaticPageTitle>
      )}
      <FileUploadProvider options={uploadStoreOptions}>
        <FileEntryUrlsContext.Provider value={urlsContextValue}>
          <DashboardLayout
            className="lg:bg-alt lg:p-8 lg:dark:bg"
            name="drive"
            onDragOver={e => {
              e.preventDefault();
              e.stopPropagation();
              e.dataTransfer.dropEffect = 'none';
            }}
            onDrop={e => {
              // prevent files from desktop from blowing away the document
              e.preventDefault();
            }}
          >
            <Navbar />
            <DashboardSidenav position="left" size="md">
              <Sidebar />
            </DashboardSidenav>
            <DashboardContent isScrollable={false}>
              <div className="dashboard-rounded-panel flex h-full flex-col border-divider-lighter">
                <DriveContentHeader />
                <FileView />
              </div>
            </DashboardContent>
            <UploadQueue />
            <DriveDialogsContainer />
            <DashboardSidenav position="right" size="lg">
              <DetailsSidebar />
            </DashboardSidenav>
          </DashboardLayout>
        </FileEntryUrlsContext.Provider>
        <EntryDragPreview />
      </FileUploadProvider>
    </Fragment>
  );
}

function Navbar() {
  const {isMobileMode} = useContext(DashboardLayoutContext);
  const activePage = useDriveStore(s => s.activePage);

  const children = isMobileMode ? null : <NavbarSearch />;
  const searchButton = (
    <IconButton elementType={Link} to={SearchPage.path}>
      <SearchIcon />
    </IconButton>
  );

  const mobileRightChildren = (
    <Fragment>
      {activePage !== SearchPage && searchButton}
      <CreateNewButton isCompact />
    </Fragment>
  );

  return (
    <Fragment>
      <DashboardNavbar
        rightChildren={isMobileMode && mobileRightChildren}
        menuPosition="drive-navbar"
        color="transparent"
        darkModeColor="transparent"
        logoColor="matchMode"
        border="border-b lg:border-none"
        textColor="text-main"
        size={null}
        className="mb-8"
      >
        {children}
      </DashboardNavbar>
      {isMobileMode && <FloatingActionList />}
    </Fragment>
  );
}

function FloatingActionList() {
  const entriesSelected = useDriveStore(s => s.selectedEntries.size);
  if (!entriesSelected) return null;
  return (
    <div className="fixed right-0 top-0 z-10 flex h-54 w-full items-center justify-center gap-10 rounded bg-primary px-6 text-on-primary shadow-xl">
      <IconButton
        onClick={() => {
          driveState().selectEntries([]);
        }}
      >
        <CloseIcon />
      </IconButton>
      <Trans message=":count selected" values={{count: entriesSelected}} />
      <EntryActionList className="ml-auto" />
    </div>
  );
}
