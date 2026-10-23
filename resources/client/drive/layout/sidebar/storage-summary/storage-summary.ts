import {appQueries} from '@app/app-queries';
import {useQuery} from '@tanstack/react-query';
import {prettyBytes} from '@ui/utils/files/pretty-bytes';

export type FetchStorageSummaryResponse = {
  used: number;
  available: number | null;
};

export function useStorageSummary() {
  return useQuery({
    ...appQueries.storageSummary.get(),
    select: formatResponse,
  });
}

function formatResponse(response: FetchStorageSummaryResponse) {
  // null means that user has unlimited space available
  const percentage =
    response.available === null
      ? 0
      : (response.used * 100) / response.available;

  return {
    usedFormatted: prettyBytes(response.used, 2),
    availableFormatted: prettyBytes(response.available, 0),
    percentage,
    used: response.used,
    available: response.available,
  };
}
