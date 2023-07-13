import Quantity from '@components/React/Quantity';
import priceFormat from '@js/utils/priceFormat';
import { useCartItemUpdate } from '@openstudio/thelia-api-utils';
import { useState } from 'react';
import { useIntl } from 'react-intl';
import Delete from '../Delete/Delete';
import { ItemProps } from '../MiniCart.types';
import Price from '../Price/Price';

function CartItem({
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
}: ItemProps) {
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
            src={(window as any).PLACEHOLDER_IMAGE}
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
            price={price.taxed * quantity}
            price_promo={promoPrice.taxed * quantity}
            isPromo={promo}
          />
        </div>
      </div>
    </div>
  );
}

export default CartItem;
