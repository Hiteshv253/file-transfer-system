import {driveInvalidateKey} from '@app/app-queries';
import {DriveEntryPermissions} from '@app/drive/files/drive-entry';
import {BackendErrorResponse} from '@common/errors/backend-error-response';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useMutation} from '@tanstack/react-query';
import {toast} from '@ui/toast/toast';
import axios from 'axios';

export interface ShareEntryPayload {
  emails: string[];
  permissions: DriveEntryPermissions;
  entryId: number;
}

function shareEntry({
  entryId,
  ...payload
}: ShareEntryPayload): Promise<BackendResponse> {
  return apiClient
    .post(`file-entries/${entryId}/share`, payload)
    .then(response => response.data);
}

export function useShareEntry() {
  return useMutation({
    mutationFn: (payload: ShareEntryPayload) => shareEntry(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({queryKey: driveInvalidateKey});
    },
    onError: err => {
      if (axios.isAxiosError(err) && err.response) {
        const response = err.response.data as BackendErrorResponse;
        if (response.errors?.emails) {
          toast.danger(response.errors?.emails[0]);
        } else {
          showHttpErrorToast(err);
        }
      }
    },
  });
}
