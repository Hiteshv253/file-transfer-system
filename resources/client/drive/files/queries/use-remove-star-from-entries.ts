import {driveInvalidateKey} from '@app/app-queries';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {Tag} from '@common/tags/tag';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {toast} from '@ui/toast/toast';

interface Response extends BackendResponse {
  tag: Tag;
}

interface Payload {
  entryIds: number[];
}

function removeStarFromEntries({entryIds}: Payload): Promise<Response> {
  return apiClient
    .post('file-entries/unstar', {entryIds})
    .then(response => response.data);
}

export function useRemoveStarFromEntries() {
  return useMutation({
    mutationFn: (payload: Payload) => removeStarFromEntries(payload),
    onSuccess: (data, {entryIds}) => {
      queryClient.invalidateQueries({queryKey: driveInvalidateKey});
      toast(
        message('Removed star from [one 1 item|other :count items]', {
          values: {count: entryIds.length},
        }),
      );
    },
    onError: err => showHttpErrorToast(err, message('Could not remove star')),
  });
}
