import {NavbarProps} from '@common/ui/navigation/navbar/navbar';
import {useNavigate} from '@common/ui/navigation/use-navigate';
import {Button} from '@ui/buttons/button';
import {ButtonColor} from '@ui/buttons/get-shared-button-style';
import {IconButton} from '@ui/buttons/icon-button';
import {Item} from '@ui/forms/listbox/item';
import {Trans} from '@ui/i18n/trans';
import {PersonIcon} from '@ui/icons/material/Person';
import {Menu, MenuTrigger} from '@ui/menu/menu-trigger';
import {Fragment} from 'react';
import {Link} from 'react-router';

interface NavbarAuthButtonsProps {
  primaryButtonColor?: ButtonColor;
  navbarColor?: NavbarProps['color'];
}
export function NavbarAuthButtons({
  primaryButtonColor,
  navbarColor,
}: NavbarAuthButtonsProps) {
  if (!primaryButtonColor) {
    primaryButtonColor = navbarColor === 'primary' ? 'elevated' : 'primary';
  }

  return (
    <Fragment>
      <MobileButtons />
      <DesktopButtons primaryButtonColor={primaryButtonColor} />
    </Fragment>
  );
}

interface DesktopButtonsProps {
  primaryButtonColor: ButtonColor;
}
function DesktopButtons({primaryButtonColor}: DesktopButtonsProps) {
  return (
    <div className="text-sm max-md:hidden">
      <Button
        elementType={Link}
        to="/login"
        variant="raised"
        color={primaryButtonColor}
      >
        <Trans message="Login" />
      </Button>
    </div>
  );
}

function MobileButtons() {
  const navigate = useNavigate();
  return (
    <MenuTrigger>
      <IconButton size="md" className="md:hidden">
        <PersonIcon />
      </IconButton>
      <Menu>
        <Item value="login" onSelected={() => navigate('/login')}>
          <Trans message="Login" />
        </Item>
      </Menu>
    </MenuTrigger>
  );
}
