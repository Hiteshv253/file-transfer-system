import {SettingsPanel} from '@common/admin/settings/layout/settings-panel';
import {Component as CommonGeneralSettings} from '@common/admin/settings/pages/general-settings';
import {useAdminSettings} from '@common/admin/settings/requests/use-admin-settings';
import {Item} from '@ui/forms/listbox/item';
import {FormSelect} from '@ui/forms/select/select';
import {Trans} from '@ui/i18n/trans';

export function Component() {
  const {data} = useAdminSettings();
  return (
    <CommonGeneralSettings
      defaultValues={{client: {homepage: {type: data.client.homepage.type}}}}
    >
      <HomepageSection />
    </CommonGeneralSettings>
  );
}

function HomepageSection() {
  return (
    <SettingsPanel
      className="mb-24"
      title={<Trans message="Homepage" />}
      description={
        <Trans message="Configure which page should be displayed as your site's homepage." />
      }
    >
      <FormSelect
        name="client.homepage.type"
        selectionMode="single"
        label={<Trans message="Site home page" />}
      >
        <Item value="landingPage">
          <Trans message="Landing page" />
        </Item>
        <Item value="loginPage">
          <Trans message="Login page" />
        </Item>
      </FormSelect>
    </SettingsPanel>
  );
}
