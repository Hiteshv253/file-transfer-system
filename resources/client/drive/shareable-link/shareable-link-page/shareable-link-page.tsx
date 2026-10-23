import {useShareableLinkPage} from '@app/drive/shareable-link/queries/use-shareable-link-page';
import {FolderPreview} from '@app/drive/shareable-link/shareable-link-page/folder-preview/folder-preview';
import {useLinkPageStore} from '@app/drive/shareable-link/shareable-link-page/link-page-store';
import {PasswordPage} from '@app/drive/shareable-link/shareable-link-page/password-page';
import {NotFoundPage} from '@common/ui/not-found-page/not-found-page';
import {FileEntryUrlsContext} from '@common/uploads/file-entry-urls';
import {ActiveWorkspaceProvider} from '@common/workspace/active-workspace-id-context';
import {useTrans} from '@ui/i18n/use-trans';
import {ProgressCircle} from '@ui/progress/progress-circle';
import {ReactElement} from 'react';
import {ShareableLinkPageFilePreview} from './shareable-link-page-file-preview';

export function Component() {
  const {status, link} = useShareableLinkPage();
  const {trans} = useTrans();
  const isPasswordProtected = useLinkPageStore(s => s.isPasswordProtected);
  const password = useLinkPageStore(s => s.password);

  let content: ReactElement;

  if (status === 'pending') {
    content = (
      <div className="flex h-screen flex-auto items-center justify-center">
        <ProgressCircle
          aria-label={trans({message: 'Loading link'})}
          isIndeterminate
        />
      </div>
    );
  } else if (!link && !isPasswordProtected) {
    return <NotFoundPage />;
  } else if (isPasswordProtected && !password) {
    content = <PasswordPage />;
  } else if (link?.entry?.type === 'folder') {
    content = <FolderPreview />;
  } else {
    content = <ShareableLinkPageFilePreview />;
  }

  return (
    <ActiveWorkspaceProvider>
      <FileEntryUrlsContext.Provider
        value={{shareable_link: link?.id, password}}
      >
        {content}
      </FileEntryUrlsContext.Provider>
    </ActiveWorkspaceProvider>
  );
}
