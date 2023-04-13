import 'leaflet/dist/leaflet.css';

import {
  FeatureGroup,
  MapContainer,
  Marker,
  Popup,
  TileLayer,
  useMap
} from 'react-leaflet';
import React, {
  createRef,
  useCallback,
  useEffect,
  useMemo,
  useState
} from 'react';
import { orderBy, size } from 'lodash-es';
import {
  useGetCheckout,
  usePickupLocations,
  useSetCheckout
} from '@openstudio/thelia-api-utils';

import Alert from '../Alert';
import Loader from '../Loader';
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
import Title from '../Title';
import { useRef } from 'react';
// import { pickupFixtures } from '../Checkout/constants';

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
            className="mt-4 Button Button--primary"
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
  const groupRef = useRef(null);

  const mapCenter = useMemo(() => {
    if (locations.length > 0) {
      const markers = locations.map((point) => {
        return {
          latitude: parseFloat(point.latitude || 0),
          longitude: parseFloat(point.longitude || 0)
        };
      });
      const center = getLatLngCenter(markers);
      return {
        center: [center.latitude, center.longitude],
        bounds: markers.map((e) => [e.latitude, e.longitude])
      };
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
      map.setView(mapCenter.center);
      map.fitBounds(mapCenter.bounds);
    }
  }, [mapCenter, selectedLocation, map]);

  return (
    <div>
      <TileLayer
        url="http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}"
        subdomains={['mt0', 'mt1', 'mt2', 'mt3']}
      />
      <FeatureGroup ref={groupRef}>
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
      </FeatureGroup>
    </div>
  );
}

function PickupPointsList({ locations, selectedLocation, onChooseLocation }) {
  const intl = useIntl();

  return (
    <div
      className="overflow-y-auto bg-white divide-y divide-gray-200 rounded-l 2xl:w-5/12"
      style={{ height: '50vh' }}
    >
      {size(locations) > 0 ? (
        orderBy(locations, ['title'], ['asc']).map((location) => {
          return (
            <div
              key={location.id}
              ref={location.refList}
              className={`border-primary flex cursor-pointer flex-wrap items-center  justify-between border-b border-opacity-50 p-4 ${
                selectedLocation?.id === location.id
                  ? 'bg-main font-bold text-white'
                  : 'bg-gray-100'
              }`}
              onClick={() => {
                onChooseLocation(location.id);
              }}
            >
              <div className="w-3/4 pr-4 mb-4 leading-tight text-left normal-case">
                <div className="font-bold">{location.title}</div>
                <div className="mt-2 text-sm">
                  {location.module?.i18n?.title}
                </div>
                {location.address ? (
                  <div className="mt-2 text-sm leading-tight ">
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

export function PickupMap({ query, defaultAddressId }) {
  const { data: checkout } = useGetCheckout();
  const { mutateAsync: setCheckout } = useSetCheckout();
  const intl = useIntl();

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
    async (id) => {
      const location = locations.find((l) => l.id === id);
      if (location?.address) {
        try {
          await setCheckout({
            ...checkout,
            pickupAddress: { ...location.address, type: 'pickup' },
            deliveryModuleId: location.moduleId,
            deliveryAddressId: null,
            deliveryModuleOptionCode: location.moduleOptionCode
          });
        } catch (error) {
          alert('Cannot select this pickup point');
        }
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
    return <Loader />;
  }

  if (error) {
    return (
      <Alert
        type="warning"
        title={intl.formatMessage({ id: 'ERROR' })}
        message={intl.formatMessage({ id: 'NO_PICKUP_POINT_LOADED' })}
      />
    );
  }

  return locations.length > 0 ? (
    <div className="lg:flex">
      <MapContainer
        center={mapCenter}
        zoom={15}
        style={{ height: '50vh', width: '100%' }}
        className="z-0 overflow-hidden"
      >
        <div>
          <div className="relative h-full py-8 overflow-hidden border-t border-gray-300 xl:flex">
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
  ) : (
    <Alert
      type="warning"
      title={intl.formatMessage({ id: 'ERROR' })}
      message={intl.formatMessage({ id: 'NO_PICKUP_POINT_LOADED' })}
    />
  );
}

export function ZipCodeSearcher({ onSubmit }) {
  const intl = useIntl();
  const { register, handleSubmit } = useForm();
  const [hasError, setHasError] = useState(false);

  return (
    <form
      className="w-full mb-4"
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
      <Title title="FIND_RELAY" className="mb-4 text-2xl Title--3" />

      <div className="PhoneCheck">
        <div className="PhoneCheck-field">
          <input
            type="number"
            id="zipcode"
            {...register('zipCode')}
            max="99999"
            min="10000"
            placeholder="ex. 75001"
            className="PhoneInput"
            required
          />
          <button type="submit" className="PhoneCheck-btn">
            {intl.formatMessage({ id: 'OK' })}
          </button>
        </div>
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

export default function Map({ addresses }) {
  const defaultAddress = addresses?.find((a) => a.isDefault === 1);

  const [query, setQuery] = useState({
    address: defaultAddress?.address1 || '',
    zipCode: defaultAddress?.zipCode || '',
    city: defaultAddress?.city || '',
    radius: 15000
  });

  return (
    <div>
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
      <PickupMap query={query} defaultAddressId={defaultAddress?.id} />
    </div>
  );
}
