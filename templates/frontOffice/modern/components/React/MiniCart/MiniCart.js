import React, {
  Suspense,
  useEffect,
  useLayoutEffect,
  useRef,
  useState
} from 'react';
import { hideCart, showLogin } from '@js/redux/modules/visibility';
import {
  useCartItemDelete,
  useCartItemUpdate,
  useCartQuery,
  useCustomer
} from '@openstudio/thelia-api-utils';
import { useDispatch, useSelector } from 'react-redux';

import AddCoupon from '../AddCoupon';
import { ReactComponent as IconCLose } from './imgs/icon-close.svg';
import Loader from '../Loader';
import priceFormat from '@utils/priceFormat';
import { useIntl } from 'react-intl';

function Price({ price, price_promo, isPromo }) {
  return (
    <div className="flex items-center">
      {isPromo === 1 ? (
        <div className="flex flex-col leading-none">
          <span className="mb-2 text-lg">{priceFormat(+price_promo)}</span>
          <span className="text-sm line-through">{priceFormat(+price)}</span>
        </div>
      ) : (
        <div className="flex flex-col">
          <span className="text-lg">{priceFormat(+price)}</span>
        </div>
      )}
    </div>
  );
}

function Quantity({ quantity, cartItemId }) {
  const { mutate, status } = useCartItemUpdate(cartItemId);

  if (!quantity || !cartItemId) return null;

  return (
    <div className="flex items-center">
      <div className=" relative flex flex-row">
        <button
          onClick={() => {
            mutate(quantity - 1);
          }}
          disabled={status === 'loading'}
          className="flex h-6 w-6 cursor-pointer items-center justify-center rounded-none bg-gray-200 focus:outline-none"
        >
          <span className="m-auto">-</span>
        </button>
        <div className="flex h-6 w-10 cursor-default items-center justify-center bg-white">
          <span>{quantity}</span>
        </div>
        <button
          onClick={() => {
            mutate(quantity + 1);
          }}
          disabled={status === 'loading'}
          className="flex h-6 w-6 cursor-pointer items-center justify-center rounded-none bg-gray-200 focus:outline-none"
        >
          <span className="m-auto">+</span>
        </button>
      </div>
    </div>
  );
}

function Delete({ id }) {
  const { mutate: deleteItem, status } = useCartItemDelete(id);

  if (!id) return null;

  if (status === 'loading')
    return (
      <div className=" absolute top-0 right-0">
        <svg
          className=" h-4 w-4 stroke-current"
          viewBox="0 0 38 38"
          xmlns="http://www.w3.org/2000/svg"
        >
          <g fill="none" fillRule="evenodd">
            <g transform="translate(1 1)" strokeWidth="2">
              <circle strokeOpacity=".5" cx="18" cy="18" r="18" />
              <path d="M36 18c0-9.94-8.06-18-18-18">
                <animateTransform
                  attributeName="transform"
                  type="rotate"
                  from="0 18 18"
                  to="360 18 18"
                  dur="1s"
                  repeatCount="indefinite"
                />
              </path>
            </g>
          </g>
        </svg>
      </div>
    );

  return (
    <button
      onClick={() => {
        deleteItem();
      }}
      className="hover: focus: text-red-500"
    >
      Supprimer
    </button>
  );
}

function Item({
  id,
  images,
  product,
  productSaleElement,
  price,
  promo,
  promoPrice,
  quantity,
  canDelete
}) {
  return (
    <div className="CartItem flex pt-4 first:pt-0">
      <div className="CartItem-contain relative">
        {images ? (
          <div className="h-16 w-16 bg-white p-2">
            <img
              src={typeof images?.[0]?.url === 'string' ? images[0].url : ''}
              className="w-100 h-100 object-contain object-center"
              alt={
                typeof images?.[0]?.i18n?.title === 'string'
                  ? images[0].i18n.title
                  : ''
              }
              loading="lazy"
            />
          </div>
        ) : null}
      </div>
      <div className="ml-6 flex-1">
        <div className="mb-4 flex items-start justify-between">
          <div>
            <a href={product.url} className="block font-bold">
              {product.i18n.title}
            </a>
            <div className="text-sm text-gray-600">
              {productSaleElement?.attributes?.map((attribute) => {
                return (
                  <div key={attribute.id}>
                    {attribute?.i18n?.title}:{' '}
                    {attribute?.values
                      ?.map((value) => value?.i18n?.title || '')
                      .join(' - ')}
                  </div>
                );
              })}
            </div>
          </div>
          {canDelete ? <Delete id={id} /> : null}
        </div>
        <div className="flex flex-wrap items-center justify-between">
          <Quantity quantity={quantity} id={id} cartItemId={id} />
          <Price
            price={price.taxed * quantity}
            price_promo={promoPrice.taxed * quantity}
            isPromo={promo}
          />
        </div>
      </div>
    </div>
  );
}

function EmptyCart() {
  const intl = useIntl();
  return (
    <legend className="font-heading text-bold mb-7 mt-10 px-10 text-center text-2xl uppercase leading-5">
      {intl.formatMessage({ id: 'CART_EMPTY' })}
    </legend>
  );
}

export function CartItems({ cart, canDelete = true }) {
  return (
    <div className="grid gap-y-4 divide-y divide-gray-200">
      {cart.items?.map((item, index) => (
        <Item key={item.id || index} canDelete={canDelete} {...item} />
      ))}
    </div>
  );
}

function FooterItem({ label, value }) {
  return (
    <dl className="flex items-center justify-between text-lg uppercase leading-none">
      <dt>{label}</dt>
      <dd>{value}</dd>
    </dl>
  );
}

function Total({ label, value }) {
  return (
    <dl className="flex items-baseline justify-between py-5 uppercase leading-none">
      <dt className="text-lg">{label}</dt>
      <dd className="text-2xl">{value}</dd>
    </dl>
  );
}

export function MiniCartFooter({ delivery, taxes, discount, coupon, total }) {
  const intl = useIntl();
  return (
    <div>
      <div className="grid gap-y-4 border-t border-b border-main border-opacity-25 py-5">
        <AddCoupon />
        <FooterItem
          label={intl.formatMessage({ id: 'TOTAL_UNTAXED' })}
          value={priceFormat(total - taxes)}
        />
        {delivery !== null ? (
          <FooterItem
            label={intl.formatMessage({ id: 'DELIVERY' })}
            value={priceFormat(delivery)}
          />
        ) : null}
        <FooterItem
          label={intl.formatMessage({ id: 'TAXES' })}
          value={priceFormat(taxes)}
        />
        {discount && coupon !== 'NO_COUPON' ? (
          <FooterItem
            label={intl.formatMessage({ id: 'DISCOUNT' })}
            value={priceFormat(discount)}
          />
        ) : null}
      </div>
      <Total
        label={intl.formatMessage({ id: 'TOTAL' })}
        value={priceFormat(total + delivery)}
      />
    </div>
  );
}

function MiniCart() {
  const dispatch = useDispatch();
  const [maxheight, setMaxHeight] = useState('15px');
  const footerRef = useRef(null);
  const { data: cart = {} } = useCartQuery();
  const { data: customer } = useCustomer();
  const intl = useIntl();

  useLayoutEffect(() => {
    if (cart) {
      const node = document.getElementById('js-cart-count');
      if (node) {
        node.innerText = `(${cart?.items?.length || 0})`;
      }
    }
  }, [cart]);

  useLayoutEffect(() => {
    if (footerRef.current) {
      setMaxHeight(`${footerRef.current.offsetHeight}px`);
    }
  }, [cart, footerRef]);

  if (!cart.items || cart.items.length === 0) {
    return <EmptyCart />;
  }

  return (
    <div>
      <button
        type="Button"
        className="hover: focus: absolute top-0 right-0 -translate-x-4 translate-y-4 transform"
        onClick={() => {
          dispatch(hideCart());
        }}
      >
        <IconCLose className="" />
      </button>
      <div className="">
        <div
          className="overflow-y-auto"
          style={{
            height: `calc(100vh - (10.125rem + ${maxheight}))`
          }}
        >
          <CartItems cart={cart} />
        </div>
      </div>
      <div className="mt-auto pt-10" ref={footerRef}>
        <MiniCartFooter {...cart} />
        <div className="flex justify-center">
          <a
            href="/order/delivery"
            className={`btn w-full  ${
              cart.items?.length <= 0 ? 'opacity-50' : ''
            }`}
            onClick={(e) => {
              if (cart.items?.length === 0) {
                e.preventDefault();
              }
              if (!customer) {
                e.preventDefault();
                dispatch(showLogin({ showCart: true }));
              }
            }}
          >
            {intl.formatMessage({ id: 'SUBMIT_CART' })}
          </a>
        </div>
      </div>
    </div>
  );
}

export default function MiniCartWrapper() {
  const visible = useSelector((state) => state.visibility.cart);
  const [isOrderDelivery, setIsOrderDelivery] = useState(false);

  useEffect(() => {
    if (document.body.classList.contains('page-order-delivery')) {
      setIsOrderDelivery(true);
    }
  }, []);
  return (
    <div
      className={`MiniCart z-10 flex flex-col bg-white p-10 ${
        visible ? 'MiniCart--visible' : ''
      } ${isOrderDelivery ? 'isOrderDelivery' : ''}`}
    >
      <Suspense fallback={<Loader size="w-24 h-24" />}>
        <MiniCart />
      </Suspense>
    </div>
  );
}
