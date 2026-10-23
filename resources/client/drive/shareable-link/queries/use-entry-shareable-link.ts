import {appQueries} from '@app/app-queries';
import {ShareableLink} from '@app/drive/shareable-link/shareable-link';
import {useQuery} from '@tanstack/react-query';

export type FetchShareableLinkResponse = {
  link: ShareableLink | null;
};

export function useEntryShareableLink(entryId: number) {
  return useQuery({
    ...appQueries.shareableLinks.getEntryShareableLink(entryId),
    enabled: !!entryId,
  });
}
