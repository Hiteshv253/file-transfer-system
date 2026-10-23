import {
  DrivePage,
  makeFolderPage,
  RootFolderPage,
  SharesPage,
  TrashPage,
} from '@app/drive/drive-page/drive-page';
import {useDriveStore} from '@app/drive/drive-store';
import {EntryActionMenuTrigger} from '@app/drive/entry-actions/entry-action-menu-trigger';
import {useFolderPath} from '@app/drive/files/queries/use-folder-path';
import {useAuth} from '@common/auth/use-auth';
import {DashboardLayoutContext} from '@common/ui/dashboard-layout/dashboard-layout-context';
import {useNavigate} from '@common/ui/navigation/use-navigate';
import {useActiveWorkspace} from '@common/workspace/active-workspace-id-context';
import {Breadcrumb} from '@ui/breadcrumbs/breadcrumb';
import {BreadcrumbItem} from '@ui/breadcrumbs/breadcrumb-item';
import {ButtonBase} from '@ui/buttons/button-base';
import {MessageDescriptor} from '@ui/i18n/message-descriptor';
import {MixedText} from '@ui/i18n/mixed-text';
import {ArrowDropDownIcon} from '@ui/icons/material/ArrowDropDown';
import {ReactNode, useContext} from 'react';

interface ItemConfig {
  page: DrivePage;
  label: MessageDescriptor | string;
}

interface PageBreadcrumbsProps {
  className?: string;
}
export function PageBreadcrumbs({className}: PageBreadcrumbsProps) {
  const {isMobileMode} = useContext(DashboardLayoutContext);
  const navigate = useNavigate();
  const page = useDriveStore(s => s.activePage);
  const folder = page?.folder;
  const query = useFolderPath({
    hash: folder?.hash,
    isEnabled: folder?.hash !== RootFolderPage.folder.hash,
  });
  const workspace = useActiveWorkspace();
  const rootItem = useRootItem();
  // wait until path, folder and workspace load fully
  const isLoading =
    !page ||
    !workspace ||
    (page.isFolderPage && !folder) ||
    query.fetchStatus !== 'idle';

  let content: ReactNode;

  if (isLoading) {
    content = null;
  } else {
    const items: ItemConfig[] = rootItem ? [rootItem] : [];

    if (query.data) {
      query.data.path.forEach(parent => {
        items.push({
          page: makeFolderPage(parent),
          label: parent.name,
        });
      });
    }

    content = (
      <Breadcrumb
        className={className}
        size={isMobileMode ? 'md' : 'lg'}
        currentIsClickable
      >
        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          if (!isLast) {
            return (
              <BreadcrumbItem
                key={item.page.uniqueId}
                onSelected={() => {
                  navigate(item.page.path);
                }}
              >
                <MixedText value={item.label} />
              </BreadcrumbItem>
            );
          }

          return (
            <BreadcrumbItem key={item.page.uniqueId}>
              {({isMenuItem}) => {
                if (
                  isMenuItem ||
                  (!item.page.folder && item.page !== TrashPage)
                ) {
                  return <MixedText value={item.label} />;
                }
                return (
                  <EntryActionMenuTrigger page={item.page} showIfNoActions>
                    <ButtonBase className="flex items-center gap-2 rounded focus-visible:ring-offset-4">
                      <MixedText value={item.label} />
                      <ArrowDropDownIcon className="text-muted icon-md" />
                    </ButtonBase>
                  </EntryActionMenuTrigger>
                );
              }}
            </BreadcrumbItem>
          );
        })}
      </Breadcrumb>
    );
  }

  return content;
}

function useRootItem(): ItemConfig | null {
  const page = useDriveStore(s => s.activePage);
  const workspace = useActiveWorkspace();
  const {user} = useAuth();

  if (!page) return null;

  // in workspace
  if (workspace && !workspace.default) {
    if (
      page?.isFolderPage &&
      (page?.name === RootFolderPage.name ||
        page.folder?.workspace_id === workspace.id)
    ) {
      return {label: workspace.name, page: RootFolderPage};
    }
  }

  if (page?.isFolderPage) {
    const owner = page.folder?.users.find(u => u.owns_entry);
    // inside shared folder
    if (owner?.id !== user?.id) {
      return {label: SharesPage.label, page: SharesPage};
    }
    // if folder is currently active, root item will always be root folder page
    return {label: RootFolderPage.label, page: RootFolderPage};
  }

  // if folder page is not active, we are already at the root
  return {label: page.label, page};
}
