import { createSlice } from '@reduxjs/toolkit';

const initialState = {
	mode: 'delivery',
	deliveryModule: null,
	deliveryModuleOption: null,
	paymentModule: null,
	deliveryAddress: null,
	billingAddress: null,
	acceptedTermsAndConditions: false,
	comment: null
};

export const checkoutSlice = createSlice({
	name: 'checkout',
	initialState,
	reducers: {
		resetCheckout: (state) => {
			state = initialState;
		},
		setMode: (state, action) => {
			state.mode = action.payload;

			state.deliveryAddress = initialState.deliveryAddress;
			state.deliveryModule = initialState.deliveryModule;
			state.deliveryModuleOption = initialState.deliveryModuleOption;
		},
		setDeliveryModule: (state, action) => {
			state.deliveryModule = action.payload.module;
			state.deliveryModuleOption = action.payload.option;
		},
		setPaymentModule: (state, action) => {
			state.paymentModule = action.payload;
		},
		setDeliveryAddress: (state, action) => {
			state.deliveryAddress = action.payload;
			if (!state.billingAddress?.touched) {
				state.billingAddress = action.payload;
			}
		},
		setBillingAddress: (state, action) => {
			state.billingAddress = action.payload;
		},
		setAcceptedTermsAndConditions: (state) => {
			state.acceptedTermsAndConditions = !state.acceptedTermsAndConditions;
		}
	}
});

export const {
	resetCheckout,
	setMode,
	setDeliveryModule,
	setPaymentModule,
	setDeliveryAddress,
	setBillingAddress,
	setAcceptedTermsAndConditions
} = checkoutSlice.actions;

export default checkoutSlice.reducer;
