import {appQueries} from '@app/app-queries';
import {DriveEntryUser} from '@app/drive/files/drive-entry';
import {useLinkPageStore} from '@app/drive/shareable-link/shareable-link-page/link-page-store';
import {BackendResponse} from '@common/http/backend-response/backend-response';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {toast} from '@ui/toast/toast';

interface Response extends BackendResponse {
  users: DriveEntryUser[];
}

interface Props {
  password: string | null;
  linkId: number;
}

function importIntoOwnDrive({linkId, password}: Props): Promise<Response> {
  return apiClient
    .post(`shareable-links/${linkId}/import`, {password})
    .then(r => r.data);
}

export function useImportIntoOwnDrive() {
  const password = useLinkPageStore(s => s.password);
  return useMutation({
    mutationFn: (props: Omit<Props, 'password'>) =>
      importIntoOwnDrive({...props, password}),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: appQueries.shareableLinks.invalidateKey,
      });
      toast(message('Item imported into your drive'));
    },
    onError: err => showHttpErrorToast(err, message('Could not create link')),
  });
}
