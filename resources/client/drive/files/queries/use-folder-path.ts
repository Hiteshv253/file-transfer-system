import {appQueries} from '@app/app-queries';
import {useQuery} from '@tanstack/react-query';

export type UserFolderPathParams = {
  shareable_link?: string | number;
  password?: string | null;
};

type Props = {
  hash?: string;
  params?: UserFolderPathParams;
  isEnabled?: boolean;
};
export function useFolderPath({hash, params, isEnabled = true}: Props) {
  return useQuery({
    ...appQueries.folderPath.get(hash!, params),
    enabled: !!hash && isEnabled,
  });
}
