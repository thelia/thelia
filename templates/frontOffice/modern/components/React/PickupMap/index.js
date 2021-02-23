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
import { useAddressQuery, usePickupLocations } from '@js/api';

import Alert from '../Alert';
import Loader from '../Loader';
import { getLatLngCenter } from '@utils/map';
import { icon } from 'leaflet/src/layer/marker/Icon';
import markerChronopostImg from './images/chronopost-logo.png';
import markerColissimoImg from './images/colissimo-logo.png';
import markerDpDImg from './images/dpd-logo.png';
import markerImg from './images/marker-icon.png';
import markerImgShadow from './images/marker-shadow.png';
import { setDeliveryAddress } from '@js/redux/modules/checkout';
import { useDispatch } from 'react-redux';
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
						className="mt-4 btn btn--sm"
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
	const [mapCenter, setMapCenter] = useState([0, 0]);

	useEffect(() => {
		if (locations.length > 0) {
			const center = getLatLngCenter(
				locations.map((point) => {
					return {
						latitude: parseFloat(point.latitude || 0),
						longitude: parseFloat(point.longitude || 0)
					};
				})
			);
			setMapCenter([center.latitude, center.longitude]);
		}
	}, [locations]);

	useEffect(() => {
		if (selectedLocation) {
			map.closePopup();
			map.flyTo([selectedLocation.latitude, selectedLocation.longitude], 16);
			map.openPopup(selectedLocation.ref.current);
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
						icon={findIcon(location.module?.code)}
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
			className="overflow-y-auto bg-white divide-y divide-gray-200 rounded-l xl:w-4/12"
			style={{ height: '50vh' }}
		>
			{size(locations) > 0 ? (
				orderBy(locations, ['title'], ['asc']).map((location) => {
					return (
						<div
							key={location.id}
							ref={location.refList}
							className={`p-4 flex flex-wrap justify-between items-center  border-b border-main border-opacity-50 cursor-pointer ${
								selectedLocation?.id === location.id
									? 'bg-main text-white'
									: 'bg-white'
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

export default function PickupMap({ module }) {
	const dispatch = useDispatch();
	const [selected, setSelected] = useState(null);

	const { data: addresses = [] } = useAddressQuery();
	const defaultAddress = addresses?.find((a) => a.default);
	const query = {
		address: defaultAddress?.address1 || '',
		zipCode: defaultAddress?.zipCode || '',
		city: defaultAddress?.city || '',
		radius: 15000
	};

	const { data: pickupPoints = [], error, isLoading } = usePickupLocations(
		query
	);

	const locations = useMemo(() => {
		if (!module || !module.id) return [];
		return pickupPoints
			.filter((l) => l.moduleId === module.id)
			.map((p) => {
				return {
					...p,
					ref: createRef(),
					refList: createRef(),
					latitude: parseFloat(p.latitude || 0),
					longitude: parseFloat(p.longitude || 0),
					module
				};
			});
	}, [pickupPoints, module]);

	const onSelect = useCallback(
		(id) => {
			const location = locations.find((l) => l.id === id);
			if (location?.address) {
				setSelected(location);
				dispatch(setDeliveryAddress({ ...location.address, type: 'pickup' }));
			}
		},
		[dispatch, locations]
	);

	if (!module) return null;

	if (isLoading) {
		return <Loader size="w-12 h-12" />;
	}

	if (error) {
		return <Alert type="error" title="Error" />;
	}

	return (
		<div className="lg:flex">
			<MapContainer
				center={[0, 0]}
				zoom={13}
				style={{ height: '50vh', width: '100%' }}
				className="flex-1 overflow-hidden "
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
	);
}
