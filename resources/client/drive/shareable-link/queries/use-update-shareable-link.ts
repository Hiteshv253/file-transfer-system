import {appQueries} from '@app/app-queries';
import {onFormQueryError} from '@common/errors/on-form-query-error';
import {apiClient, queryClient} from '@common/http/query-client';
import {useMutation} from '@tanstack/react-query';
import {UseFormReturn} from 'react-hook-form';
import {FetchShareableLinkResponse} from './use-entry-shareable-link';

export type UpdateShareableLinkPayload = {
  allowEdit: boolean;
  allowDownload: boolean;
  allowDirect: boolean;
  expiresAt: string;
  password: string;
  entryId: number;
};

function updateShareableLink({
  entryId,
  ...payload
}: UpdateShareableLinkPayload): Promise<FetchShareableLinkResponse> {
  return apiClient
    .put(`file-entries/${entryId}/shareable-link`, payload)
    .then(response => response.data);
}

export function useUpdateShareableLink(
  form: UseFormReturn<UpdateShareableLinkPayload>,
) {
  return useMutation({
    mutationFn: (payload: UpdateShareableLinkPayload) =>
      updateShareableLink(payload),
    onSuccess: (data, {entryId}) => {
      queryClient.setQueryData<FetchShareableLinkResponse>(
        appQueries.shareableLinks.getEntryShareableLink(entryId).queryKey,
        data,
      );
    },
    onError: r => onFormQueryError(r, form),
  });
}
