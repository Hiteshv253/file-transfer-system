import {AdminSettings} from '@common/admin/settings/admin-settings';
import {AdminSettingsLayout} from '@common/admin/settings/layout/settings-layout';
import {SettingsPanel} from '@common/admin/settings/layout/settings-panel';
import {useAdminSettings} from '@common/admin/settings/requests/use-admin-settings';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {Item} from '@ui/forms/listbox/item';
import {FormRadio} from '@ui/forms/radio-group/radio';
import {FormRadioGroup} from '@ui/forms/radio-group/radio-group';
import {FormSelect} from '@ui/forms/select/select';
import {FormSwitch} from '@ui/forms/toggle/switch';
import {Trans} from '@ui/i18n/trans';
import {useSettings} from '@ui/settings/use-settings';
import {useForm, useWatch} from 'react-hook-form';

export function Component() {
  const {data} = useAdminSettings();
  const form = useForm<AdminSettings>({
    defaultValues: {
      client: {
        drive: {
          default_view: data.client.drive?.default_view ?? 'list',
          send_share_notification:
            data.client.drive?.send_share_notification ?? false,
          default_available_space:
            data.client.drive?.default_available_space ?? 1024 * 1024 * 1024,
          direct_links: data.client.drive?.direct_links ?? false,
          copy_link_default:
            data.client.drive?.copy_link_default ?? 'shareable',
        },
        share: {
          suggest_emails: data.client.share?.suggest_emails ?? false,
        },
      },
    },
  });

  return (
    <AdminSettingsLayout form={form} title={<Trans message="Drive" />}>
      <div className="space-y-24">
        <ViewModePanel />
        <ShareNotificationsPanel />
        <EmailSuggestionPanel />
        <DefaultAvailableSpacePanel />
        <DirectLinkPanel />
        <CopyLinkDefaultPanel />
      </div>
    </AdminSettingsLayout>
  );
}

function ViewModePanel() {
  return (
    <SettingsPanel
      title={<Trans message="Default view mode" />}
      description={
        <Trans message="Configure which view mode should be used by default in the drive user dashboard." />
      }
    >
      <FormRadioGroup
        required
        name="client.drive.default_view"
        orientation="vertical"
      >
        <FormRadio value="list">
          <Trans message="List" />
        </FormRadio>
        <FormRadio value="grid">
          <Trans message="Grid" />
        </FormRadio>
      </FormRadioGroup>
    </SettingsPanel>
  );
}

function ShareNotificationsPanel() {
  return (
    <SettingsPanel
      title={<Trans message="Share notifications" />}
      description={
        <Trans message="Send a notification to user when a file or folder is shared with them." />
      }
    >
      <FormSwitch name="client.drive.send_share_notification">
        <Trans message="Enable notifications" />
      </FormSwitch>
    </SettingsPanel>
  );
}

function EmailSuggestionPanel() {
  return (
    <SettingsPanel
      title={<Trans message="Email suggestions" />}
      description={
        <Trans message="Suggest email address of existing users when sharing a file or folder." />
      }
    >
      <FormSwitch name="client.share.suggest_emails">
        <Trans message="Enable suggestions" />
      </FormSwitch>
    </SettingsPanel>
  );
}

function DefaultAvailableSpacePanel() {
  return (
    <SettingsPanel
      title={<Trans message="Default available space" />}
      description={
        <Trans message="You can override this by editing permissions for a specific user, role or subscription plan in admin area." />
      }
    >
      <FormTextField
        type="number"
        name="client.drive.default_available_space"
        endAdornment={
          <span className="pr-12 text-xs text-muted">
            <Trans message="Bytes" />
          </span>
        }
      />
    </SettingsPanel>
  );
}

function DirectLinkPanel() {
  const {base_url} = useSettings();
  return (
    <SettingsPanel
      title={<Trans message="Direct links" />}
      description={
        <Trans
          message="These links will show file content directly, instead of it being embedded inside share page."
          values={{baseUrl: base_url}}
        />
      }
    >
      <FormSwitch name="client.drive.direct_links">
        <Trans message="Allow copying direct links" />
      </FormSwitch>
    </SettingsPanel>
  );
}

function CopyLinkDefaultPanel() {
  const directLinksEnabled = useWatch({name: 'client.drive.direct_links'});
  if (!directLinksEnabled) {
    return null;
  }
  return (
    <SettingsPanel
      title={<Trans message="Default link to copy" />}
      description={
        <Trans message="Whether shareable or direct link should be copied to clipboard by default." />
      }
    >
      <FormSelect
        label={<Trans message="Copy link default" />}
        labelDisplay="hidden"
        name="client.drive.copy_link_default"
      >
        <Item value="shareable">
          <Trans message="Shareable link" />
        </Item>
        <Item value="direct">
          <Trans message="Direct link" />
        </Item>
      </FormSelect>
    </SettingsPanel>
  );
}
