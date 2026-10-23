import {driveInvalidateKey} from '@app/app-queries';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {getAxiosErrorMessage} from '@common/http/get-axios-error-message';
import {apiClient, queryClient} from '@common/http/query-client';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {MessageDescriptor} from '@ui/i18n/message-descriptor';
import {toast} from '@ui/toast/toast';

interface Response extends BackendResponse {}

interface Payload {
  entryIds: number[];
  emptyTrash?: boolean;
  deleteForever?: boolean;
}

function deleteEntries(payload: Payload): Promise<Response> {
  return apiClient
    .post('file-entries/delete', payload)
    .then(response => response.data);
}

export function useDeleteEntries() {
  return useMutation({
    mutationFn: (payload: Payload) => {
      toast.loading(getLoaderMessage(payload), {disableExitAnimation: true});
      return deleteEntries(payload);
    },
    onSuccess: (r, {entryIds, emptyTrash}) => {
      queryClient.invalidateQueries({queryKey: driveInvalidateKey});
      if (emptyTrash) {
        toast(message('Emptied trash'), {disableEnterAnimation: true});
      } else {
        toast(
          message('Permanently deleted [one 1 item|other :count items]', {
            values: {count: entryIds.length},
          }),
          {disableEnterAnimation: true},
        );
      }
    },
    onError: (err, {emptyTrash}) => {
      const backendError = getAxiosErrorMessage(err);
      if (backendError) {
        toast.danger(backendError, {disableEnterAnimation: true});
      } else if (emptyTrash) {
        toast.danger('could not empty trash', {disableEnterAnimation: true});
      } else {
        toast.danger('Could not delete items', {disableEnterAnimation: true});
      }
    },
  });
}

function getLoaderMessage(payload: Payload): MessageDescriptor {
  if (payload.emptyTrash) {
    return message('Emptying trash...');
  } else {
    return message('Deleting files...');
  }
}
