import {driveInvalidateKey} from '@app/app-queries';
import {DriveEntry} from '@app/drive/files/drive-entry';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {toast} from '@ui/toast/toast';

interface Response extends BackendResponse {
  entries: DriveEntry[];
}

interface Payload {
  entryIds: number[];
  destinationId?: number;
}

function duplicateEntries(payload: Payload): Promise<Response> {
  return apiClient
    .post('file-entries/duplicate', payload)
    .then(response => response.data);
}

export function useDuplicateEntries() {
  return useMutation({
    mutationFn: (payload: Payload) => {
      toast.loading(
        message('Duplicating [one 1 item|other :count items]...', {
          values: {
            count: payload.entryIds.length,
          },
        }),
        {disableExitAnimation: true},
      );
      return duplicateEntries(payload);
    },
    onSuccess: (r, p) => {
      queryClient.invalidateQueries({queryKey: driveInvalidateKey});
      toast(
        message('Duplicated [one 1 item|other :count items]', {
          values: {count: p.entryIds.length},
        }),
        {disableEnterAnimation: true},
      );
    },
    onError: err =>
      showHttpErrorToast(err, message('Could not duplicate items'), null, {
        disableEnterAnimation: true,
      }),
  });
}
