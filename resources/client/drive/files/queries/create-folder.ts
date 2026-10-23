import {driveInvalidateKey} from '@app/app-queries';
import {DriveFolder} from '@app/drive/files/drive-entry';
import {onFormQueryError} from '@common/errors/on-form-query-error';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {useMutation} from '@tanstack/react-query';
import {UseFormReturn} from 'react-hook-form';

interface Response extends BackendResponse {
  folder: DriveFolder;
}

interface CreateFolderProps {
  name: string;
  parentId?: number | null;
}

function createFolder({name, parentId}: CreateFolderProps): Promise<Response> {
  return apiClient
    .post('folders', {
      name,
      parentId: parentId === 0 ? null : parentId,
    })
    .then(response => response.data);
}

export function useCreateFolder(form: UseFormReturn<any>) {
  return useMutation({
    mutationFn: ({name, parentId}: CreateFolderProps) => {
      return createFolder({name, parentId});
    },
    onSuccess: () =>
      queryClient.invalidateQueries({queryKey: driveInvalidateKey}),
    onError: r => onFormQueryError(r, form),
  });
}
