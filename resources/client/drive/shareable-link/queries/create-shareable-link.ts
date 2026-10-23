import {appQueries} from '@app/app-queries';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {FetchShareableLinkResponse} from './use-entry-shareable-link';

interface Payload {
  entryId: number;
  enableDirectLinks?: boolean;
}
export function useCreateShareableLink() {
  return useMutation({
    mutationFn: (payload: Payload) => createShareableLink(payload),
    onSuccess: (data, {entryId}) => {
      queryClient.setQueryData<FetchShareableLinkResponse>(
        appQueries.shareableLinks.getEntryShareableLink(entryId).queryKey,
        data,
      );
    },
    onError: err => showHttpErrorToast(err, message('Could not create link')),
  });
}

function createShareableLink(
  payload: Payload,
): Promise<FetchShareableLinkResponse> {
  if (!payload.entryId) {
    return Promise.reject(new Error('Invalid entry id'));
  }
  return apiClient
    .post(`file-entries/${payload.entryId}/shareable-link`, {
      enableDirectLinks: payload.enableDirectLinks,
    })
    .then(response => response.data);
}
