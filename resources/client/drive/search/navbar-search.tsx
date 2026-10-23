import {useNavigate} from '@common/ui/navigation/use-navigate';
import {IconButton} from '@ui/buttons/icon-button';
import {TextField} from '@ui/forms/input-field/text-field/text-field';
import {useTrans} from '@ui/i18n/use-trans';
import {SearchIcon} from '@ui/icons/material/Search';
import {useState} from 'react';
import {useSearchParams} from 'react-router';
import {SearchPage} from '@app/drive/drive-page/drive-page';
import {useDriveStore} from '@app/drive/drive-store';

export function NavbarSearch() {
  const {trans} = useTrans();
  const navigate = useNavigate();
  const activePage = useDriveStore(s => s.activePage);
  const [searchParams] = useSearchParams();
  const [inputValue, setInputValue] = useState(searchParams.get('query') || '');

  return (
    <form
      className="ml-10 flex-auto"
      onSubmit={e => {
        e.preventDefault();
        navigate(
          {
            pathname: SearchPage.path,
            search: `?query=${inputValue}`,
          },
          {replace: true},
        );
      }}
    >
      <TextField
        size="lg"
        background="bg-elevated"
        inputRadius="rounded-full"
        inputBorder="border border-divider-lighter"
        inputShadow="shadow-sm"
        inputClassName="max-h-46 placeholder:text-main/50"
        value={inputValue}
        onChange={e => setInputValue(e.target.value)}
        onFocus={() => {
          if (activePage !== SearchPage) {
            navigate(SearchPage.path);
          }
        }}
        startAdornment={
          <IconButton type="submit">
            <SearchIcon />
          </IconButton>
        }
        className="max-w-720 flex-auto"
        placeholder={trans({message: 'Search files and folders'})}
        aria-label={trans({message: 'Search files and folders'})}
      />
    </form>
  );
}
