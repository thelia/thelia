import 'leaflet/dist/leaflet.css';

import { MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet';
import React, {
  createRef,
  useCallback,
  useEffect,
  useMemo,
  useState
} from 'react';
import { orderBy, size } from 'lodash-es';
import {
  useAddressQuery,
  useGetCheckout,
  usePickupLocations,
  useSetCheckout
} from '@openstudio/thelia-api-utils';

import Alert from '../Alert';
import Input from '../Input';
import Loader from '../Loader';
import SubmitButton from '../SubmitButton';
import axios from 'axios';
import { getLatLngCenter } from '@utils/map';
import { icon } from 'leaflet/src/layer/marker/Icon';
import markerChronopostImg from './images/chronopost-logo.png';
import markerColissimoImg from './images/colissimo-logo.png';
import markerDpDImg from './images/dpd-logo.png';
import markerImg from './images/marker-icon.png';
import markerImgShadow from './images/marker-shadow.png';
import { useForm } from 'react-hook-form';
import { useIntl } from 'react-intl';

const customIcon = icon({
  iconUrl: markerImg,
  shadowUrl: markerImgShadow,
  iconSize: [25, 41],
  shadowSize: [41, 41]
});
const chronopostCustomIcon = icon({
  iconUrl: markerChronopostImg,
  iconSize: [25, 41]
});
const colissimoCustomIcon = icon({
  iconUrl: markerColissimoImg,
  iconSize: [25, 41]
});
const dpdCstomIcon = icon({
  iconUrl: markerDpDImg,
  iconSize: [25, 41]
});

const WEEKDAYS = [
  'Lundi',
  'Mardi',
  'Mercredi',
  'Jeudi',
  'Vendredi',
  'Samedi',
  'Dimanche'
];

function findIcon(code) {
  switch (code) {
    case 'ColissimoPickupPoint':
      return colissimoCustomIcon;
    case 'ChronopostPickupPoint':
      return chronopostCustomIcon;
    case 'DpdPickup':
      return dpdCstomIcon;
    default:
      return customIcon;
  }
}

function InfoPopUp({ location, selected, onChooseLocation }) {
  return (
    <Popup ref={location.ref}>
      <div className="p-2 text-center">
        <div className="font-bold">{location.title}</div>
        {location.module?.i18n?.title ? (
          <div className="mt-2 font-bold">{location.module?.i18n?.title}</div>
        ) : null}
        {location.address ? (
          <div className="mt-4">
            {location.address.address1} <br />
            {location.address.city} {location.address.zipcode}
          </div>
        ) : null}
        {location.openingHours ? (
          <div className="mt-4">
            {location.openingHours.map((d, index) =>
              d ? (
                <div key={index} className="flex justify-between">
                  <span className="font-bold">{WEEKDAYS[index]}</span>
                  <span className="ml-4">{d}</span>
                </div>
              ) : null
            )}
          </div>
        ) : null}
        {!selected ? (
          <button
            type="button"
            className="btn btn--sm mt-4"
            onClick={() => {
              onChooseLocation(location.id);
              if (location.refList?.current) {
                location.refList.current.scrollIntoView({
                  behavior: 'smooth',
                  block: 'center',
                  inline: 'center'
                });
              }
            }}
          >
            Choisir
          </button>
        ) : null}
      </div>
    </Popup>
  );
}

function MapDisplay({ locations, selectedLocation, onChooseLocation }) {
  const map = useMap();

  const mapCenter = useMemo(() => {
    if (locations.length > 0) {
      const center = getLatLngCenter(
        locations.map((point) => {
          return {
            latitude: parseFloat(point.latitude || 0),
            longitude: parseFloat(point.longitude || 0)
          };
        })
      );
      return [center.latitude, center.longitude];
    }
  }, [locations]);

  useEffect(() => {
    if (selectedLocation) {
      if (selectedLocation.ref.current._latlng) {
        map.closePopup();
        map.flyTo([selectedLocation.latitude, selectedLocation.longitude], 16);
        map.openPopup(selectedLocation.ref.current);
      }
    } else if (mapCenter) {
      map.setView(mapCenter);
    }
  }, [mapCenter, selectedLocation, map]);

  return (
    <div>
      <TileLayer
        url="http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}"
        subdomains={['mt0', 'mt1', 'mt2', 'mt3']}
      />
      {locations.map((location) => {
        return (
          <Marker
            key={location.id}
            position={[location.latitude, location.longitude]}
            icon={findIcon(location?.moduleOptionCode)}
          >
            <InfoPopUp
              location={location}
              selected={selectedLocation?.id === location.id}
              onChooseLocation={onChooseLocation}
            />
          </Marker>
        );
      })}
    </div>
  );
}

function PickupPointsList({ locations, selectedLocation, onChooseLocation }) {
  const intl = useIntl();

  return (
    <div
      className="divide-y divide-gray-200 overflow-y-auto rounded-l bg-white xl:w-4/12"
      style={{ height: '50vh' }}
    >
      {size(locations) > 0 ? (
        orderBy(locations, ['title'], ['asc']).map((location) => {
          return (
            <div
              key={location.id}
              ref={location.refList}
              className={`flex cursor-pointer flex-wrap items-center justify-between  border-b border-main border-opacity-50 p-4 ${
                selectedLocation?.id === location.id
                  ? 'bg-main text-white'
                  : 'bg-white'
              }`}
              onClick={() => {
                onChooseLocation(location.id);
              }}
            >
              <div className="mb-4 w-3/4 pr-4 text-left normal-case leading-tight">
                <div className="font-bold">{location.title}</div>
                <div className="mt-2 text-sm">
                  {location.module?.i18n?.title}
                </div>
                {location.address ? (
                  <div className=" mt-2 text-sm leading-tight">
                    {location.address.address1} <br />
                    {location.address.city} {location.address.zipcode}
                  </div>
                ) : null}
              </div>
            </div>
          );
        })
      ) : (
        <div className="p-4">
          {intl.formatMessage({ id: 'NO_RELAY_POINT' })}
        </div>
      )}
    </div>
  );
}

export function PickupMap({ query }) {
  const { data: checkout } = useGetCheckout();
  const { mutate: setCheckout } = useSetCheckout();

  const {
    data: pickupPoints = [],
    error,
    isLoading
  } = usePickupLocations(query);

  const locations = useMemo(() => {
    if (!checkout?.deliveryModuleId) return [];
    return pickupPoints
      .filter((l) => l.moduleId === checkout?.deliveryModuleId)
      .map((p) => {
        return {
          ...p,
          ref: createRef(),
          refList: createRef(),
          latitude: parseFloat(p.latitude || 0),
          longitude: parseFloat(p.longitude || 0),
          moduleOptionCode: checkout?.deliveryModuleOptionCode
        };
      });
  }, [
    pickupPoints,
    checkout?.deliveryModuleId,
    checkout?.deliveryModuleOptionCode
  ]);

  const onSelect = useCallback(
    (id) => {
      const location = locations.find((l) => l.id === id);
      if (location?.address) {
        setCheckout({
          ...checkout,
          pickupAddress: { ...location.address, type: 'pickup' },
          deliveryModuleId: location.moduleId,
          deliveryModuleOptionCode: location.moduleOptionCode
        });
      }
    },
    [locations, checkout, setCheckout]
  );

  const selected = useMemo(() => {
    let match = null;
    match = locations.find(
      (location) => location.id === checkout?.pickupAddress?.id
    );
    return match;
  }, [checkout, locations]);

  const mapCenter = useMemo(() => {
    if (locations.length > 0) {
      const center = getLatLngCenter(
        locations.map((point) => {
          return {
            latitude: parseFloat(point.latitude || 0),
            longitude: parseFloat(point.longitude || 0)
          };
        })
      );
      return [center.latitude, center.longitude];
    }
  }, [locations]);

  if (isLoading) {
    return <Loader size="w-12 h-12" />;
  }

  if (error) {
    return <Alert type="error" title="Error" />;
  }

  return locations.length > 0 ? (
    <div className="lg:flex">
      <MapContainer
        center={mapCenter}
        zoom={13}
        style={{ height: '50vh', width: '100%' }}
        className=" flex-1 overflow-hidden"
      >
        <div>
          <div className="relative h-full overflow-hidden border-t border-gray-300 py-8 xl:flex">
            <MapDisplay
              locations={locations}
              selectedLocation={selected}
              onChooseLocation={onSelect}
            />
          </div>
        </div>
      </MapContainer>

      <PickupPointsList
        locations={locations}
        selectedLocation={selected}
        onChooseLocation={onSelect}
      />
    </div>
  ) : null;
}

export function ZipCodeSearcher({ onSubmit }) {
  const intl = useIntl();
  const { register, handleSubmit } = useForm();
  const [hasError, setHasError] = useState(false);

  return (
    <form
      className="mb-4 w-full"
      onSubmit={handleSubmit(async (values) => {
        try {
          setHasError(false);
          const res = await axios.get(
            `https://geo.api.gouv.fr/communes?codePostal=${values.zipCode}`
          );
          onSubmit(values.zipCode, res.data[0].nom);
        } catch (e) {
          setHasError(true);
        }
      })}
    >
      <div className="mb-3 flex-1 text-xl font-bold">
        {intl.formatMessage({ id: 'FIND_RELAY' })}
      </div>

      <div className="flex items-stretch">
        <Input
          id="zipcode"
          {...register('zipCode')}
          placeholder="ex. 75001"
          className="h-auto w-full"
          type="number"
          max="99999"
          min="10000"
          required
        />
        <SubmitButton
          type="submit"
          className="py-0"
          label={intl.formatMessage({ id: 'OK' })}
        />
      </div>
      {hasError && (
        <Alert
          type="error"
          message={intl.formatMessage({ id: 'INCORRECT_ZIPCODE' })}
        />
      )}
    </form>
  );
}

export default function Map() {
  const { data: addresses = [] } = useAddressQuery();
  const defaultAddress = addresses?.find((a) => a.isDefault);

  const [query, setQuery] = useState({
    address: defaultAddress?.address1 || '',
    zipCode: defaultAddress?.zipCode || '',
    city: defaultAddress?.city || '',
    radius: 15000
  });

  return (
    <div className="panel p-5 shadow">
      {defaultAddress?.countryCode === 'FR' ? (
        <ZipCodeSearcher
          onSubmit={(zipcode, city) =>
            setQuery({
              address: ' ',
              zipCode: zipcode,
              city: city,
              radius: 15000
            })
          }
        />
      ) : null}
      <PickupMap query={query} />
    </div>
  );
}
