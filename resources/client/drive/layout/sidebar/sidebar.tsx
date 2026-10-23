import {useAuth} from '@common/auth/use-auth';
import {useNavigate} from '@common/ui/navigation/use-navigate';
import {WorkspaceSelector} from '@common/workspace/workspace-selector';
import {Button} from '@ui/buttons/button';
import {Trans} from '@ui/i18n/trans';
import {useSettings} from '@ui/settings/use-settings';
import clsx from 'clsx';
import {Link} from 'react-router';
import {RootFolderPage} from '@app/drive/drive-page/drive-page';
import {CreateNewButton} from '@app/drive/layout/create-new-button';
import {SidebarMenu} from '@app/drive/layout/sidebar/sidebar-menu';
import {StorageMeter} from '@app/drive/layout/sidebar/storage-summary/storage-meter';

interface SidebarProps {
  className?: string;
}
export function Sidebar({className}: SidebarProps) {
  const {isSubscribed} = useAuth();
  const {billing} = useSettings();
  return (
    <div
      className={clsx(
        className,
        'flex flex-col gap-20 border-divider-lighter bg text-sm font-medium text-muted lg:mr-8 lg:bg-transparent',
      )}
    >
      <div className="compact-scrollbar flex-auto overflow-y-auto">
        <CreateNewButton className="px-12 pt-28 text-center" />
        <SidebarMenu />
        <StorageMeter />
        {billing.enable ? (
          <div className="mt-14 pl-60">
            <Button
              elementType={Link}
              to={isSubscribed ? '/billing/change-plan' : '/pricing'}
              variant="outline"
              color="primary"
              size="xs"
            >
              <Trans message="Upgrade" />
            </Button>
          </div>
        ) : null}
      </div>
      <WorkspaceSwitcher />
    </div>
  );
}

function WorkspaceSwitcher() {
  const navigate = useNavigate();
  return (
    <WorkspaceSelector
      onChange={() => {
        navigate(RootFolderPage.path);
      }}
      className="mt-auto w-full flex-shrink-0 border-t px-24 py-18"
    />
  );
}
