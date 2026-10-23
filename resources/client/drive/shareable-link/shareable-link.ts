import {FileEntry} from '@common/uploads/file-entry';

export interface ShareableLink {
  id: number;
  hash: string;
  password: string;
  user_id: number;
  entry_id: number;
  entry?: FileEntry;
  expires_at: string;
  allow_edit: boolean;
  allow_download: boolean;
  allow_direct: boolean;
}

export type GetShareableLinkParams = {
  loader: 'shareableLinkPage' | 'shareableLink';
  page?: number;
  order?: string;
  password?: string | null;
};
