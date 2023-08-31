import { PickupLocation } from '@js/types/common';

interface Location extends PickupLocation {
  ref: React.RefObject<any>;
  refList: React.RefObject<any>;
}

export type InfoPopupProps = {
  location: Location;
  selected: boolean;
  onChooseLocation: (id: string) => Promise<void>;
};

export type MapDisplayProps = {
  locations: Location[];
  selectedLocation: Location | undefined;
  onChooseLocation: (id: string) => Promise<void>;
};

export type PickupMapProps = {
  query: {
    address: string;
    zipCode: string;
    city: string;
    radius: number;
  };
  defaultAddressId?: number;
};

export type ZipCodeSearcherProps = {
  onSubmit: (zipcode: string, city: string) => void;
};
