import {driveState, useDriveStore} from '@app/drive/drive-store';
import {EntriesSortButton} from '@app/drive/layout/sorting/entries-sort-button';
import React from 'react';

interface DriveSortButtonProps {
  isDisabled?: boolean;
}
export function DriveSortButton({isDisabled}: DriveSortButtonProps) {
  const descriptor = useDriveStore(s => s.sortDescriptor);
  return (
    <EntriesSortButton
      isDisabled={isDisabled}
      descriptor={descriptor}
      onChange={value => {
        driveState().setSortDescriptor(value);
      }}
    />
  );
}
