import {useActiveDialogEntry} from '@app/drive/drive-store';
import {
  getDirectLink,
  getShareableLink,
} from '@app/drive/entry-actions/get-public-access-link';
import {DriveEntry} from '@app/drive/files/drive-entry';
import type {ShareDialogActivePanel} from '@app/drive/share-dialog/share-dialog';
import {useCreateShareableLink} from '@app/drive/shareable-link/queries/create-shareable-link';
import {useDeleteShareableLink} from '@app/drive/shareable-link/queries/use-delete-shareable-link';
import {useEntryShareableLink} from '@app/drive/shareable-link/queries/use-entry-shareable-link';
import {ShareableLink} from '@app/drive/shareable-link/shareable-link';
import {Button} from '@ui/buttons/button';
import {TextField} from '@ui/forms/input-field/text-field/text-field';
import {Switch} from '@ui/forms/toggle/switch';
import {Trans} from '@ui/i18n/trans';
import {useTrans} from '@ui/i18n/use-trans';
import {ContentCopyIcon} from '@ui/icons/material/ContentCopy';
import {useSettings} from '@ui/settings/use-settings';
import useClipboard from '@ui/utils/hooks/use-clipboard';
import {randomString} from '@ui/utils/string/random-string';

interface ShareableLinkPanelProps {
  setActivePanel: (name: ShareDialogActivePanel) => void;
  entry: DriveEntry;
}
export function ShareableLinkPanel({
  setActivePanel,
  entry,
}: ShareableLinkPanelProps) {
  const query = useEntryShareableLink(entry.id);
  const linkExists = !!query.data?.link;
  const createLink = useCreateShareableLink();
  const deleteLink = useDeleteShareableLink();
  const isLoading =
    query.isLoading || createLink.isPending || deleteLink.isPending;
  return (
    <div>
      <div className="mb-14 font-semibold">
        <Trans message="Public access" />
      </div>
      <div className="flex items-center justify-between gap-14 px-2 pb-4">
        <Switch
          checked={linkExists}
          disabled={isLoading}
          onChange={() => {
            if (linkExists) {
              deleteLink.mutate({entryId: entry.id});
            } else {
              createLink.mutate({entryId: entry.id, enableDirectLinks: true});
            }
          }}
        >
          {linkExists ? (
            <Trans message="Shareable link is created" />
          ) : (
            <Trans message="Create shareable link" />
          )}
        </Switch>
        {linkExists && (
          <Button
            variant="link"
            color="primary"
            onClick={() => {
              setActivePanel('linkSettings');
            }}
          >
            <Trans message="Link settings" />
          </Button>
        )}
      </div>
      <ShareableLinkInput link={query.data?.link} />
    </div>
  );
}

interface ShareableLinkInputProps {
  link?: ShareableLink | null;
}
function ShareableLinkInput({link}: ShareableLinkInputProps) {
  const {drive} = useSettings();
  const {trans} = useTrans();
  const entry = useActiveDialogEntry();
  const hash = link?.hash || entry?.hash || randomString();
  const linkUrl = getShareableLink(hash);
  const [isCopied, setCopied] = useClipboard(linkUrl, {
    successDuration: 1000,
  });
  return (
    <div>
      <TextField
        disabled={!link}
        className="mt-10"
        readOnly
        value={linkUrl}
        aria-label={trans({message: 'Shareable link'})}
        onFocus={e => {
          (e.target as HTMLInputElement).select();
        }}
        endAdornment={
          <Button
            className="mr-6"
            size="xs"
            variant="flat"
            color="primary"
            onClick={setCopied}
            startIcon={<ContentCopyIcon />}
          >
            {isCopied ? <Trans message="Copied!" /> : <Trans message="Copy" />}
          </Button>
        }
      />
      {link && entry && drive?.direct_links && entry.type !== 'folder' ? (
        <CopyDirectLinkButton link={link} entry={entry} />
      ) : null}
    </div>
  );
}

type CopyDirectLinkButtonProps = {
  link: ShareableLink;
  entry: DriveEntry;
};
function CopyDirectLinkButton({link, entry}: CopyDirectLinkButtonProps) {
  const [isCopied, setCopied] = useClipboard(getDirectLink(link, entry), {
    successDuration: 1000,
  });
  return (
    <Button
      variant="outline"
      className="mt-10"
      size="xs"
      onClick={() => setCopied()}
    >
      {isCopied ? (
        <Trans message="Copied direct link!" />
      ) : (
        <Trans message="Copy direct link" />
      )}
    </Button>
  );
}
