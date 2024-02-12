import {
  Suspense,
  useLayoutEffect,
  useRef,
  useState,
  useEffect,
  Dispatch,
  SetStateAction,
  LegacyRef
} from 'react';
import messages, { locale } from '../intl';
import {
  useCartItemDelete,
  useCartItemUpdate,
  useCartQuery,
  useCustomer
} from '@openstudio/thelia-api-utils';

import { queryClient } from '@openstudio/thelia-api-utils';
import AddCoupon from '../AddCoupon';
import { ReactComponent as IconCLose } from '@icons/close.svg';
import { ReactComponent as IconTrash } from '@icons/trash.svg';
import useLockBodyScroll from '@utils/useLockBodyScroll';
import { useClickAway } from 'react-use';
import Loader from '../Loader';
import priceFormat from '@utils/priceFormat';
import { IntlProvider, useIntl } from 'react-intl';
import Quantity from '../Quantity';
import useEscape from '@js/utils/useEscape';
import closeAndFocus from '@js/utils/closeAndFocus';
import { trapTabKey } from '@js/standalone/trapItemsMenu';
import { Cart, Image, Product, ProductSaleElement } from '@js/types/common';
import { useGlobalVisibility } from '@js/state/visibility';
import { createRoot } from 'react-dom/client';
import { QueryClientProvider } from 'react-query';

function Price({
  price,
  price_promo,
  isPromo
}: {
  price: number;
  price_promo: number;
  isPromo?: boolean;
}) {
  return (
    <div className="flex items-center text-lg">
      {isPromo ? (
        <div className="flex flex-col items-end font-bold leading-none">
          <span className="mb-1">{priceFormat(+price_promo)}</span>
          <span className="text-sm font-normal line-through">
            {priceFormat(+price)}
          </span>
        </div>
      ) : (
        <div className="flex flex-col items-end font-bold leading-none">
          {priceFormat(+price)}
        </div>
      )}
    </div>
  );
}

function Delete({
  id,
  setRemoveItem,
  visible
}: {
  id: number;
  setRemoveItem: Dispatch<SetStateAction<Boolean>>;
  visible: boolean;
}) {
  const { mutate: deleteItem, status } = useCartItemDelete(id);

  useEffect(() => {
    status === 'loading' ? setRemoveItem(true) : setRemoveItem(false);
  }, [status, setRemoveItem]);

  if (!id) return null;

  return (
    <button
      onClick={() => deleteItem()}
      className="focus: outline-gray-600"
      tabIndex={visible ? 0 : -1}
    >
      <IconTrash className="h-[17px] w-[12px]" />
    </button>
  );
}

export function Item({
  id,
  product,
  productSaleElement,
  price,
  promo,
  promoPrice,
  quantity,
  canDelete,
  canChangeQuantity,
  recap,
  images = [],
  visible
}: {
  id: number;
  product: Product;
  productSaleElement: ProductSaleElement;
  price: { taxed: string | number };
  promo: boolean;
  promoPrice: { taxed: string | number };
  quantity: number;
  canDelete: boolean;
  canChangeQuantity: boolean;
  recap: boolean;
  images: Image[];
  visible: boolean;
}) {
  const [removeItem, setRemoveItem] = useState(false);
  const { mutate, status } = useCartItemUpdate(id);

  const intl = useIntl();

  return (
    <div
      className={`CartItem ${
        removeItem || status === 'loading'
          ? 'pointer-events-none opacity-50'
          : ''
      } ${recap ? 'CartItem--recap' : ''}`}
    >
      {images.length > 0 ? (
        <div className="CartItem-img">
          {images ? (
            <img
              src={`/legacy-image-library/product_image_${images?.[0]?.id}/full/!106,/0/default.webp`}
              alt={
                typeof images?.[0]?.i18n?.title === 'string'
                  ? `${images?.[0]?.i18n?.title} ${intl.formatMessage({
                      id: 'VISUAL_OF_CART'
                    })}`
                  : `${product?.i18n.title} ${intl.formatMessage({
                      id: 'VISUAL_OF_CART'
                    })}`
              }
              title={
                typeof images?.[0]?.i18n?.title === 'string'
                  ? `${images?.[0]?.i18n?.title} ${intl.formatMessage({
                      id: 'VISUAL_OF_CART'
                    })}`
                  : `${product?.i18n.title} ${intl.formatMessage({
                      id: 'VISUAL_OF_CART'
                    })}`
              }
              loading="lazy"
            />
          ) : null}
        </div>
      ) : (
        <div className="CartItem-img">
          <img
            //@ts-ignore
            src={window.PLACEHOLDER_IMAGE}
            alt={
              typeof images?.[0]?.i18n?.title === 'string'
                ? `${images?.[0]?.i18n?.title} ${intl.formatMessage({
                    id: 'VISUAL_OF_CART'
                  })}`
                : `${product?.i18n.title} ${intl.formatMessage({
                    id: 'VISUAL_OF_CART'
                  })}`
            }
            title={
              typeof images?.[0]?.i18n?.title === 'string'
                ? `${images?.[0]?.i18n?.title} ${intl.formatMessage({
                    id: 'VISUAL_OF_CART'
                  })}`
                : `${product?.i18n.title} ${intl.formatMessage({
                    id: 'VISUAL_OF_CART'
                  })}`
            }
            loading="lazy"
          />
        </div>
      )}
      <div className="CartItem-contain">
        <div className="item-center flex justify-between">
          <a
            href={product.url}
            className="mr-4 block font-bold"
            tabIndex={visible ? 0 : -1}
          >
            {product.i18n.title}
          </a>
          {canDelete ? (
            <Delete id={id} setRemoveItem={setRemoveItem} visible={visible} />
          ) : null}
        </div>
        <div className="text-sm leading-none text-gray-600 ">
          <div>
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
          <div>
            {intl.formatMessage({ id: 'UNIT_PRICE' })}:{' '}
            {priceFormat(price.taxed)}
          </div>
        </div>
        <div className=" CartItem-bottom">
          {canChangeQuantity ? (
            <Quantity
              max={productSaleElement?.quantity || 0}
              mutate={mutate}
              quantity={quantity}
              small={true}
              visible={visible}
            />
          ) : (
            <span className="CartItem-smallQuantity">x{quantity}</span>
          )}
          <Price
            price={(price.taxed as number) * quantity}
            price_promo={(promoPrice.taxed as number) * quantity}
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
    <legend className="Title Title--3 text-center">
      <button
        type="button"
        className="SideBar-close"
        aria-label={intl.formatMessage({ id: 'CLOSE_CART' })}
        data-close-cart
      >
        <IconCLose className="pointer-events-none h-3 w-3" />
      </button>
      {intl.formatMessage({ id: 'CART_EMPTY' })}
    </legend>
  );
}

export function CartItems({
  cart,
  canDelete = true,
  canChangeQuantity = true,
  recap = false,
  noOverflow = false,
  visible
}: {
  cart: Cart;
  canDelete?: boolean;
  canChangeQuantity?: boolean;
  recap?: boolean;
  noOverflow?: boolean;
  visible: boolean;
}) {
  return (
    <div className="CartItems-wrapper">
      <div
        className={`CartItems ${recap ? 'CartItems--recap' : ''} ${
          noOverflow ? 'max-h-max overflow-visible' : ''
        }`}
      >
        {cart.items?.map((item, index) => (
          <Item
            key={item.id || index}
            canDelete={canDelete}
            canChangeQuantity={canChangeQuantity}
            recap={recap}
            visible={visible}
            {...item}
          />
        ))}
      </div>
    </div>
  );
}

function FooterItem({ label, value }: { label: string; value: string }) {
  return (
    <dl className="flex items-center justify-between text-lg uppercase leading-none">
      <dt>{label}</dt>
      <dd>{value}</dd>
    </dl>
  );
}

function Total({ label, value }: { label: string; value: string }) {
  return (
    <dl className="flex flex-col items-baseline justify-between leading-none">
      <dt className="text-sm text-gray-600">{label}</dt>
      <dd className="text-3xl font-medium">{value}</dd>
    </dl>
  );
}

export function MiniCartFooter({
  delivery,
  taxes,
  discount,
  coupon,
  total
}: {
  delivery: number;
  taxes: number;
  discount: number;
  coupon: string;
  total: number;
}) {
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

function MiniCart({
  visible,
  redirect
}: {
  visible: boolean;
  redirect: boolean;
}) {
  const { actions } = useGlobalVisibility();
  const { hideCart, showLogin } = actions;

  const ref = useRef<HTMLElement>(null);
  const focusRef = useRef<HTMLButtonElement>(null);
  const { data: cart = {} } = useCartQuery();

  const { data: customer } = useCustomer(true);
  const intl = useIntl();
  const [totalQuantityCart, setTotalQuantityCart] = useState(
    cart?.items?.length || 0
  );

  useLayoutEffect(() => {
    if (cart) {
      let count = 0;
      (cart as Cart)?.items?.forEach((el) => {
        count = count + (el?.quantity || 0);
      });
      setTotalQuantityCart(count);
    }
  }, [cart]);

  useEffect(() => {
    const node = document.getElementById('js-cart-count');
    if (node) {
      node.innerText = `${totalQuantityCart || 0}`;
    }
  }, [totalQuantityCart]);

  useLockBodyScroll(ref, visible, redirect);

  useClickAway(ref, (e) => {
    if (!(e.target as HTMLElement)?.matches('[data-toggle-cart]') && visible) {
      closeAndFocus(() => hideCart(), '[data-toggle-cart]');
    }
  });

  useLayoutEffect(() => {
    if (visible) {
      focusRef.current?.focus();
    }
  }, [focusRef, visible]);

  useEscape(ref, () => closeAndFocus(() => hideCart(), '[data-toggle-cart]'));

  useEffect(() => {
    const onKeydown = (e: KeyboardEvent) => {
      if (!visible) return;

      trapTabKey(ref.current as HTMLElement, e);
    };

    ref?.current?.addEventListener('keydown', onKeydown);

    return () => {
      ref?.current?.removeEventListener('keydown', onKeydown);
    };
  }, []);

  return (
    <div
      ref={ref as LegacyRef<HTMLDivElement>}
      className="grid grid-cols-1 gap-8"
    >
      <button
        ref={focusRef}
        type="button"
        aria-label={intl.formatMessage({ id: 'CLOSE_CART' })}
        className="SideBar-close"
        data-close-cart
        onClick={() => closeAndFocus(() => hideCart(), '[data-toggle-cart]')}
        tabIndex={visible ? 0 : -1}
      >
        <IconCLose className="pointer-events-none h-3 w-3" />
      </button>
      {!cart.items || cart.items.length === 0 ? (
        <EmptyCart />
      ) : (
        <>
          <div className="flex items-center justify-between ">
            <strong className="Title Title--3">mon panier</strong>
            <span className="block text-base font-bold text-gray-600">
              {totalQuantityCart +
                ' article' +
                (totalQuantityCart > 1 ? 's' : '')}
            </span>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-4">
            <Total
              label={intl.formatMessage({ id: 'TOTAL' })}
              value={priceFormat(cart.total + cart.delivery)}
            />
            <a
              href="/order/delivery"
              className={`Button ${
                cart.items?.length <= 0 ? 'opacity-50' : ''
              }`}
              onClick={(e) => {
                if (cart.items?.length === 0) {
                  e.preventDefault();
                }
                if (!customer) {
                  e.preventDefault();
                  showLogin(true);
                }
              }}
              tabIndex={visible ? 0 : -1}
            >
              {intl.formatMessage({ id: 'SUBMIT_CART' })}
            </a>
          </div>
          <CartItems cart={cart} visible={visible} />
        </>
      )}
    </div>
  );
}

export function MiniCartWrapper() {
  const { visibilityState } = useGlobalVisibility();
  const { redirectionToCheckout: redirect, cart: visible } = visibilityState;

  return (
    <div className={`SideBar ${visible ? 'SideBar--visible' : ''} `}>
      <Suspense fallback={<Loader />}>
        {visible ? <MiniCart visible={visible} redirect={redirect} /> : null}
      </Suspense>
    </div>
  );
}

function MiniCartContainer() {
  const { actions } = useGlobalVisibility();
  const { toggleCart, hideCart } = actions;

  useEffect(() => {
    const onClick = (e: Event) => {
      if ((e.target as HTMLElement)?.matches('[data-toggle-cart]')) {
        toggleCart();
      } else if ((e.target as HTMLElement)?.matches('[data-close-cart]')) {
        hideCart();
      }
    };
    document.addEventListener('click', onClick, false);

    return () => {
      document.removeEventListener('click', onClick);
    };
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <IntlProvider locale={locale} messages={(messages as any)[locale]}>
        <MiniCartWrapper />
      </IntlProvider>
    </QueryClientProvider>
  );
}

export default function MiniCartRender() {
  const DOMElement = document.querySelector('.MiniCart-root');

  if (!DOMElement) return;

  const root = createRoot(DOMElement);

  root.render(<MiniCartContainer></MiniCartContainer>);
}
