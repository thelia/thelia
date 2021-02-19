import { QueryClient, useMutation, useQuery } from 'react-query';

import axios from 'axios';

export const queryClient = new QueryClient({
	defaultOptions: {
		queries: {
			retry: false,
			staleTime: Infinity,
			refetchOnWindowFocus: false,
			refetchOnMount: false,
			refetchInterval: false,
			suspense: true
		}
	}
});

const baseURL = `${
	window.location.href.includes('index_dev.php') ? '/index_dev.php' : ''
}/open_api`;

export default async function fetcher(request) {
	try {
		const response = await axios(request || {});
		return response.data;
	} catch (error) {
		throw error;
	}
}

// PRODUCT
// ----------------

export function useSearchQuery(value, limit = 10) {
	return useQuery(
		['search', value, limit],
		() =>
			fetcher({
				url: `${baseURL}/product/search?title=${value}&limit=${limit}`
			}),
		{
			enabled: !!value
		}
	);
}

// ADDRESS
export function useAddressQuery() {
	return useQuery('addresses', () =>
		fetcher({
			url: `${baseURL}/address`
		})
	);
}

export function useAddressCreate() {
	return useMutation(
		(data) =>
			fetcher({
				url: `${baseURL}/address`,
				method: 'POST',
				data
			}),
		{
			onSuccess: () => {
				queryClient.invalidateQueries('addresses');
			}
		}
	);
}

export function useAddressUpdate() {
	return useMutation(
		({ id, data }) =>
			fetcher({
				url: `${baseURL}/address/${id}`,
				method: 'PATCH',
				data
			}),
		{
			onSuccess: () => {
				queryClient.invalidateQueries('addresses');
			}
		}
	);
}

export function useAddressDelete() {
	return useMutation(
		(id) =>
			fetcher({
				url: `${baseURL}/address/${id}`,
				method: 'DELETE'
			}),
		{
			onSuccess: () => {
				queryClient.invalidateQueries('addresses');
			}
		}
	);
}

// DELIVERY MODULES

export function useDeliveryModulessQuery(addressId) {
	const { data } = useDeliveryModes();
	return useQuery(
		['delivery_modules', addressId],
		() =>
			fetcher({
				url: `${baseURL}/delivery/modules`,
				params: {
					addressId
				}
			}),
		{
			enabled: !!data
		}
	);
}

export function useDeliveryModes() {
	const { data, ...query } = useQuery(['delivery_modes'], () =>
		fetcher({
			url: `${baseURL}/delivery/modules`
		})
	);

	return { ...query, data: [...new Set(data.map((m) => m.deliveryMode))] };
}

// PAYMENT MODULES
export function usePaymentModulessQuery() {
	return useQuery(
		['payment_modules'],
		() =>
			fetcher({
				url: `${baseURL}/payment/modules`
			}),
		{
			suspense: true
		}
	);
}

// PICKUP LOCATIONS
export function usePickupLocations(params = {}) {
	const res = useQuery(
		['pickup_locations', params],
		() => {
			return fetcher({
				url: `${baseURL}/delivery/pickup-locations`,
				method: 'GET',
				params
			});
		},
		{
			enabled: params.address && params.city && params.zipCode && true,
			suspense: false
		}
	);

	return res;
}

export function useFindAddress(q = null) {
	return useQuery(
		'find_address',
		() =>
			fetcher({
				url: `https://nominatim.openstreetmap.org/search?format=json`,
				method: 'GET',
				params: {
					bounded: 1,
					addressdetails: 1,
					extratags: 1,
					dedupe: 1,
					q
				}
			}),
		{
			enabled: !!q
		}
	);
}

export function useCheckoutCreate() {
	return useMutation(
		(data) =>
			fetcher({
				url: `${baseURL}/checkout`,
				method: 'POST',
				data
			}),
		{
			onSuccess: (data) => {
				if (data.isComplete) {
					window.location = `${window.location.origin}/order/pay`;
				}
			}
		}
	);
}

// CART
export function useCartQuery() {
	return useQuery(
		'cart',
		() =>
			fetcher({
				url: `${baseURL}/cart`
			}),
		{
			onSuccess: () => {
				queryClient.invalidateQueries('delivery_modules');
				queryClient.invalidateQueries('payment_modules');
			}
		}
	);
}

export async function addToCart({ pseId, quantity, append = true }) {
	try {
		const cartResponse = await fetcher({
			url: `${baseURL}/cart/add`,
			method: 'POST',
			data: {
				pseId,
				quantity,
				append
			}
		});
		return cartResponse;
	} catch (error) {
		throw error;
	}
}

export function useCartItemCreate() {
	return useMutation(
		({ pseId, quantity, append = true, customizations }) =>
			addToCart({ pseId, quantity, append, customizations }),
		{
			onSuccess: (data) => {
				if (data.cart) {
					queryClient.setQueryData('cart', data.cart);
				}
			}
		}
	);
}

export function useCartItemUpdate(id) {
	return useMutation(
		(quantity) =>
			fetcher({
				url: `${baseURL}/cart/${id}`,
				method: 'PATCH',
				data: {
					quantity
				}
			}),
		{
			onSuccess: (data) => {
				if (data.cart) {
					queryClient.setQueryData('cart', data.cart);
				}
			}
		}
	);
}

export function useCartItemDelete(id) {
	return useMutation(
		() =>
			fetcher({
				url: `${baseURL}/cart/${id}`,
				method: 'DELETE'
			}),
		{
			onSuccess: (data) => {
				queryClient.setQueryData('cart', data);
			}
		}
	);
}

// COUPON
export function useCouponCreate() {
	return useMutation(
		(code) =>
			fetcher({
				url: `${baseURL}/coupon`,
				method: 'POST',
				data: {
					code
				}
			}),
		{
			onSuccess: () => {
				queryClient.invalidateQueries('cart');
			}
		}
	);
}

// CUSTOMER
export function useCustomer() {
	return useQuery(
		'customer',
		() =>
			fetcher({
				url: `${baseURL}/customer`
			}),
		{
			suspense: false
		}
	);
}

// LOGIN
export function useLogin(reload = true) {
	return useMutation(
		({ email, password, rememberMe = true }) =>
			fetcher({
				url: `${baseURL}/login`,
				method: 'POST',
				data: {
					email,
					password,
					rememberMe
				}
			}),
		{
			onSuccess: () => {
				if (reload) {
					window.location.reload();
				} else {
					queryClient.invalidateQueries('customer');
				}
			}
		}
	);
}

export function useLogout() {
	return useMutation(
		() =>
			fetcher({
				url: `${baseURL}/logout`,
				method: 'POST'
			}),
		{
			onSuccess: () => {
				queryClient.setQueryData('customer', undefined);
			}
		}
	);
}
