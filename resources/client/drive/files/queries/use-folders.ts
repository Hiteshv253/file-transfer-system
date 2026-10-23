import {appQueries} from '@app/app-queries';
import {DriveFolder} from '@app/drive/files/drive-entry';
import {useAuth} from '@common/auth/use-auth';
import {useActiveWorkspaceId} from '@common/workspace/active-workspace-id-context';
import {useQuery} from '@tanstack/react-query';

export type UserFoldersApiParams = {
  userId: number;
  workspaceId: number | null;
};

export type UserFoldersResponse = {
  folders: DriveFolder[];
  rootFolder: DriveFolder;
};

export function useFolders() {
  const {user} = useAuth();
  const {workspaceId} = useActiveWorkspaceId();
  const params: UserFoldersApiParams = {
    userId: user!.id,
    workspaceId,
  };
  return useQuery({
    ...appQueries.userFolders.index(params),
    enabled: !!user,
  });
}
