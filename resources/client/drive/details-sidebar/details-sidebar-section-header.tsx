import clsx from 'clsx';
import {ReactNode} from 'react';

interface Props {
  children: ReactNode;
  margin?: string;
  className?: string;
}
export function DetailsSidebarSectionHeader({
  children,
  margin = 'mb-20',
}: Props) {
  return (
    <div className={clsx('text-base font-medium text-main', margin)}>
      {children}
    </div>
  );
}
