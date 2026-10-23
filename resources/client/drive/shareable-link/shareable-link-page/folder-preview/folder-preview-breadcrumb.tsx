import {DriveFolder} from '@app/drive/files/drive-entry';
import {useFolderPath} from '@app/drive/files/queries/use-folder-path';
import {ShareableLink} from '@app/drive/shareable-link/shareable-link';
import {useLinkPageStore} from '@app/drive/shareable-link/shareable-link-page/link-page-store';
import {Breadcrumb} from '@ui/breadcrumbs/breadcrumb';
import {BreadcrumbItem} from '@ui/breadcrumbs/breadcrumb-item';
import clsx from 'clsx';
import {ReactElement, ReactNode} from 'react';
import {useNavigateToSubfolder} from './use-navigate-to-subfolder';

interface Props {
  className?: string;
  folder?: DriveFolder;
  link: ShareableLink;
}
export function FolderPreviewBreadcrumb({className, folder, link}: Props) {
  const navigateToSubfolder = useNavigateToSubfolder();
  const password = useLinkPageStore(s => s.password);
  const query = useFolderPath({
    hash: folder?.hash,
    params: {
      shareable_link: link.id,
      password,
    },
  });

  let content: ReactNode;

  if (query.isLoading) {
    content = null;
  } else {
    const items: {folder: DriveFolder; label: ReactElement}[] = [];
    if (query.data) {
      query.data.path.forEach(parent => {
        items.push({
          folder: parent,
          label: <>{parent.name}</>,
        });
      });
    }

    content = (
      <Breadcrumb size="lg" isNavigation>
        {items.map(item => {
          return (
            <BreadcrumbItem
              onSelected={() => {
                navigateToSubfolder(item.folder.hash);
              }}
              key={item.folder.hash}
            >
              {item.label}
            </BreadcrumbItem>
          );
        })}
      </Breadcrumb>
    );
  }

  return <div className={clsx('h-36 flex-shrink-0', className)}>{content}</div>;
}
