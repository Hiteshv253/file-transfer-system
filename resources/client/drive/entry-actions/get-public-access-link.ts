import {DriveEntry} from '@app/drive/files/drive-entry';
import {ShareableLink} from '@app/drive/shareable-link/shareable-link';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';

export function getShareableLink(linkOrHash: ShareableLink | string) {
  const baseUrl = getBootstrapData().settings.base_url;
  return `${baseUrl}/drive/s/${typeof linkOrHash === 'string' ? linkOrHash : linkOrHash.hash}`;
}

export function getDirectLink(link: ShareableLink, entry: DriveEntry) {
  const baseUrl = getBootstrapData().settings.base_url;
  return `${baseUrl}/d/${link.hash}/${entry.hash}.${entry.extension}`;
}
