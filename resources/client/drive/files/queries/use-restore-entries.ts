import {driveInvalidateKey} from '@app/app-queries';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {toast} from '@ui/toast/toast';

interface Response extends BackendResponse {}

interface Payload {
  entryIds: number[];
}

export function useRestoreEntries() {
  return useMutation({
    mutationFn: (payload: Payload) => restoreEntries(payload),
    onSuccess: (r, p) => {
      queryClient.invalidateQueries({queryKey: driveInvalidateKey});
      toast(
        message('Restored [one 1 item|other :count items]', {
          values: {count: p.entryIds.length},
        }),
      );
    },
    onError: err => showHttpErrorToast(err, message('Could not restore items')),
  });
}

function restoreEntries(payload: Payload): Promise<Response> {
  return apiClient
    .post('file-entries/restore', payload)
    .then(response => response.data);
}
