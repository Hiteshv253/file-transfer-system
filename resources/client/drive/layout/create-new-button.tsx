import {driveState, useDriveStore} from '@app/drive/drive-store';
import {useDriveUploadQueue} from '@app/drive/uploading/use-drive-upload-queue';
import {Button} from '@ui/buttons/button';
import {IconButton} from '@ui/buttons/icon-button';
import {Trans} from '@ui/i18n/trans';
import {AddIcon} from '@ui/icons/material/Add';
import {CreateNewFolderIcon} from '@ui/icons/material/CreateNewFolder';
import {DriveFolderUploadIcon} from '@ui/icons/material/DriveFolderUpload';
import {FileUploadIcon} from '@ui/icons/material/FileUpload';
import {UploadFileIcon} from '@ui/icons/material/UploadFile';
import {Menu, MenuItem, MenuTrigger} from '@ui/menu/menu-trigger';
import {openUploadWindow} from '@ui/utils/files/open-upload-window';

interface CreateNewButtonProps {
  isCompact?: boolean;
  className?: string;
}
export function CreateNewButton({isCompact, className}: CreateNewButtonProps) {
  const activePage = useDriveStore(s => s.activePage);
  const {uploadFiles} = useDriveUploadQueue();

  const button = isCompact ? (
    <IconButton size="md" disabled={!activePage?.canUpload}>
      <AddIcon />
    </IconButton>
  ) : (
    <Button
      className="w-full"
      color="primary"
      variant="flat"
      size="sm"
      startIcon={<FileUploadIcon />}
      disabled={!activePage?.canUpload}
    >
      <Trans message="Upload" />
    </Button>
  );

  return (
    <div className={className}>
      <MenuTrigger
        onItemSelected={async value => {
          if (value === 'uploadFiles') {
            uploadFiles(await openUploadWindow({multiple: true}));
          } else if (value === 'uploadFolder') {
            uploadFiles(await openUploadWindow({directory: true}));
          } else if (value === 'newFolder') {
            const activeFolder = driveState().activePage?.folder;
            driveState().setActiveActionDialog(
              'newFolder',
              activeFolder ? [activeFolder] : [],
            );
          }
        }}
      >
        {button}
        <Menu>
          <MenuItem value="uploadFiles" startIcon={<UploadFileIcon />}>
            <Trans message="Upload files" />
          </MenuItem>
          <MenuItem value="uploadFolder" startIcon={<DriveFolderUploadIcon />}>
            <Trans message="Upload folder" />
          </MenuItem>
          <MenuItem value="newFolder" startIcon={<CreateNewFolderIcon />}>
            <Trans message="Create folder" />
          </MenuItem>
        </Menu>
      </MenuTrigger>
    </div>
  );
}
