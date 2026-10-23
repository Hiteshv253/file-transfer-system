import {driveInvalidateKey} from '@app/app-queries';
import {onFormQueryError} from '@common/errors/on-form-query-error';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {FileEntry} from '@common/uploads/file-entry';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {toast} from '@ui/toast/toast';
import {UseFormReturn} from 'react-hook-form';

interface Response extends BackendResponse {
  fileEntry: FileEntry;
}

interface Payload {
  entryId: number;
  name: string;
  initialName: string;
}

export function useRenameEntry(form: UseFormReturn<any>) {
  return useMutation({
    mutationFn: (payload: Payload) => renameEntry(payload),
    onSuccess: (r, p) => {
      queryClient.invalidateQueries({queryKey: driveInvalidateKey});
      toast(
        message(':oldName renamed to :newName', {
          values: {oldName: p.initialName, newName: r.fileEntry.name},
        }),
      );
    },
    onError: err => onFormQueryError(err, form),
  });
}

function renameEntry({entryId, ...payload}: Payload): Promise<Response> {
  return apiClient
    .put(`file-entries/${entryId}`, payload)
    .then(response => response.data);
}
