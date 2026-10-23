import {driveInvalidateKey} from '@app/app-queries';
import {DriveEntryUser} from '@app/drive/files/drive-entry';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {useMutation} from '@tanstack/react-query';

interface Response extends BackendResponse {
  users: DriveEntryUser[];
}

interface Payload {
  userId: number | 'me';
  entryIds: number[];
}

export function useUnshareEntries() {
  return useMutation({
    mutationFn: (payload: Payload) => unshareEntries(payload),
    onSuccess: () => {
      return queryClient.invalidateQueries({queryKey: driveInvalidateKey});
    },
  });
}

function unshareEntries({entryIds, ...payload}: Payload): Promise<Response> {
  return apiClient
    .post(`file-entries/${entryIds.join(',')}/unshare`, payload)
    .then(response => response.data);
}
