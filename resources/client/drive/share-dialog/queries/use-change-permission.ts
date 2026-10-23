import {driveInvalidateKey} from '@app/app-queries';
import {DriveEntryPermissions} from '@app/drive/files/drive-entry';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {toast} from '@ui/toast/toast';

export interface ChangePermissionsPayload {
  userId: number;
  permissions: DriveEntryPermissions;
  entryId: number;
}

export function useChangePermission() {
  return useMutation({
    mutationFn: (payload: ChangePermissionsPayload) =>
      changePermission(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({queryKey: driveInvalidateKey});
      toast(message('Updated user permissions'));
    },
    onError: err =>
      showHttpErrorToast(err, message('Could not update permissions')),
  });
}

function changePermission({
  entryId,
  ...payload
}: ChangePermissionsPayload): Promise<BackendResponse> {
  return apiClient
    .put(`file-entries/${entryId}/change-permissions`, payload)
    .then(response => response.data);
}
