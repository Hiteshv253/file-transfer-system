import {linkPageState} from '@app/drive/shareable-link/shareable-link-page/link-page-store';
import {apiClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';

interface Props {
  password: string;
  linkHash: string;
}

export function useCheckLinkPassword() {
  return useMutation({
    mutationFn: ({password, linkHash}: Props) =>
      apiClient
        .post<{matches: boolean}>(
          `shareable-link-page/${linkHash}/check-password`,
          {
            password,
          },
        )
        .then(r => r.data),
    onSuccess: (response, props) => {
      if (response.matches) {
        linkPageState().setPassword(props.password);
      }
    },
    onError: err => showHttpErrorToast(err, message('Could not create link')),
  });
}
