import {appQueries} from '@app/app-queries';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {FetchShareableLinkResponse} from './use-entry-shareable-link';

interface DeleteLinkParams {
  entryId: number;
}

function deleteShareableLink({
  entryId,
}: DeleteLinkParams): Promise<BackendResponse> {
  return apiClient
    .delete(`file-entries/${entryId}/shareable-link`)
    .then(r => r.data);
}

interface Payload {
  entryId: number;
}
export function useDeleteShareableLink() {
  return useMutation({
    mutationFn: ({entryId}: Payload) => deleteShareableLink({entryId}),
    onSuccess: (response, {entryId}) => {
      queryClient.setQueryData<FetchShareableLinkResponse>(
        appQueries.shareableLinks.getEntryShareableLink(entryId).queryKey,
        {...response, link: null},
      );
    },
    onError: err => showHttpErrorToast(err, message('Could not delete link')),
  });
}
