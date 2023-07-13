import { useCartItemDelete } from '@openstudio/thelia-api-utils';
import { useEffect } from 'react';
import { DeleteProps } from '../MiniCart.types';
import { ReactComponent as IconTrash } from '@icons/trash.svg';

function Delete({ id, setRemoveItem, visible }: DeleteProps) {
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

export default Delete;
