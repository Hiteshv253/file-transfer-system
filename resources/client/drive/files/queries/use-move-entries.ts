import {driveInvalidateKey} from '@app/app-queries';
import {RootFolderPage} from '@app/drive/drive-page/drive-page';
import {DriveEntry} from '@app/drive/files/drive-entry';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {FileEntry} from '@common/uploads/file-entry';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {toast} from '@ui/toast/toast';

interface Response extends BackendResponse {
  entries: DriveEntry[];
  destination: FileEntry | null;
}

interface Payload {
  entryIds: number[];
  destinationId?: number | null;
}

export function useMoveEntries() {
  return useMutation({
    mutationFn: (payload: Payload) => {
      toast.loading(
        message('Moving [one 1 item|other :count items]...', {
          values: {
            count: payload.entryIds.length,
          },
        }),
        {disableExitAnimation: true},
      );
      return moveEntries(payload);
    },
    onSuccess: (r, p) => {
      queryClient.invalidateQueries({queryKey: driveInvalidateKey});
      toast(
        message('Moved [one 1 item|other :count items] to ":destination"', {
          values: {
            count: p.entryIds.length,
            destination: (r.destination || RootFolderPage.folder).name,
          },
        }),
        {disableEnterAnimation: true},
      );
    },
    onError: err =>
      showHttpErrorToast(err, message('Could not move items'), null, {
        disableEnterAnimation: true,
      }),
  });
}

function moveEntries(payload: Payload): Promise<Response> {
  // backend expects null for root folder, it might be zero or empty string on frontend
  payload.destinationId = !payload.destinationId ? null : payload.destinationId;
  return apiClient
    .post('file-entries/move', payload)
    .then(response => response.data);
}
