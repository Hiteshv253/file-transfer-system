import {appQueries} from '@app/app-queries';
import {LandingPage as FoundationLandingPage} from '@common/ui/landing-page/landing-page';
import {LandingPageContext} from '@common/ui/landing-page/landing-page-context';
import {useSuspenseQuery} from '@tanstack/react-query';
import {CodeIcon} from '@ui/icons/material/Code';
import {DashboardIcon} from '@ui/icons/material/Dashboard';
import {EmailIcon} from '@ui/icons/material/Email';
import {FileUploadIcon} from '@ui/icons/material/FileUpload';
import {GroupsIcon} from '@ui/icons/material/Groups';
import {LinkIcon} from '@ui/icons/material/Link';
import {LockIcon} from '@ui/icons/material/Lock';
import {PictureAsPdfIcon} from '@ui/icons/material/PictureAsPdf';
import {SearchIcon} from '@ui/icons/material/Search';
import {ShareIcon} from '@ui/icons/material/Share';
import {SyncIcon} from '@ui/icons/material/Sync';
import {VideoLibraryIcon} from '@ui/icons/material/VideoLibrary';

const defaultIcons = {
  fileUpload: FileUploadIcon,
  dashboard: DashboardIcon,
  lock: LockIcon,
  sync: SyncIcon,
  search: SearchIcon,
  code: CodeIcon,
  email: EmailIcon,
  share: ShareIcon,
  link: LinkIcon,
  groups: GroupsIcon,
  videoLibrary: VideoLibraryIcon,
  pictureAsPdf: PictureAsPdfIcon,
};

export function Component() {
  const query = useSuspenseQuery(appQueries.landingPageData.get());
  return (
    <LandingPageContext.Provider
      value={{
        defaultIcons,
        sections: query.data.sections ?? [],
        adSlotAfterHero: 'landing-top',
      }}
    >
      <FoundationLandingPage />
    </LandingPageContext.Provider>
  );
}
