import {useNormalizedModels} from '@common/ui/normalized-model/use-normalized-models';
import {Avatar} from '@ui/avatar/avatar';
import {Button} from '@ui/buttons/button';
import {
  ChipField,
  ChipValue,
} from '@ui/forms/input-field/chip-field/chip-field';
import {Item} from '@ui/forms/listbox/item';
import {Trans} from '@ui/i18n/trans';
import {useTrans} from '@ui/i18n/use-trans';
import {useSettings} from '@ui/settings/use-settings';
import {isEmail} from '@ui/utils/string/is-email';
import {useState} from 'react';
import {DriveEntry} from '@app/drive/files/drive-entry';
import {MemberList} from './member-list';
import {
  PermissionSelector,
  PermissionSelectorItem,
  PermissionSelectorItems,
} from './permission-selector';
import {useShareEntry} from './queries/use-share-entry';

interface SharePanelProps {
  className?: string;
  entry: DriveEntry;
}
export function SharePanel({className, entry}: SharePanelProps) {
  const {trans} = useTrans();
  const {share} = useSettings();
  const shareEntry = useShareEntry();
  const [chips, setChips] = useState<ChipValue[]>([]);
  const [isSharing, setIsSharing] = useState(false);
  const [selectedPermission, setSelectedPermission] =
    useState<PermissionSelectorItem>(PermissionSelectorItems[0]);
  const allEmailsValid = chips.every(chip => !chip.invalid);
  const [inputValue, setInputValue] = useState('');
  const query = useNormalizedModels(
    'normalized-models/user',
    {perPage: 7, query: inputValue},
    {enabled: share?.suggest_emails},
  );

  // show user's email, instead of name in the chip
  const displayWith = (chip: ChipValue) => chip.description || chip.name;

  return (
    <div className={className}>
      <ChipField
        value={chips}
        onChange={setChips}
        isAsync
        isLoading={query.fetchStatus === 'fetching'}
        inputValue={inputValue}
        onInputValueChange={setInputValue}
        suggestions={query.data?.results}
        displayWith={displayWith}
        validateWith={chip => {
          const invalid = !isEmail(chip.description);
          return {
            ...chip,
            invalid,
            errorMessage: invalid
              ? trans({message: 'Not a valid email'})
              : undefined,
          };
        }}
        placeholder={trans({message: 'Enter email addresses'})}
        label={<Trans message="Invite people" />}
        labelClassName="font-semibold"
      >
        {user => (
          <Item
            value={user.id}
            startIcon={<Avatar circle src={user.image} alt="" />}
            description={user.description}
          >
            {user.name}
          </Item>
        )}
      </ChipField>
      <div className="mt-14 flex items-center justify-between gap-14">
        <PermissionSelector
          onChange={setSelectedPermission}
          value={selectedPermission}
        />
        {chips.length ? (
          <Button
            variant="flat"
            color="primary"
            size="sm"
            disabled={isSharing || !allEmailsValid}
            onClick={() => {
              setIsSharing(true);
              shareEntry.mutate(
                {
                  emails: chips.map(c => displayWith(c)),
                  permissions: selectedPermission.value,
                  entryId: entry.id,
                },
                {
                  onSuccess: () => {
                    setChips([]);
                  },
                  onSettled: () => {
                    setIsSharing(false);
                  },
                },
              );
            }}
          >
            <Trans message="Share" />
          </Button>
        ) : null}
      </div>
      <MemberList className="mt-30" entry={entry} />
    </div>
  );
}
