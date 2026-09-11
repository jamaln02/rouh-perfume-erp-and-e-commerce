import { useAppDispatch, useAppSelector } from '@/store';
import { cartSliceName, addItem, removeItem, updateQuantity, clearCart } from '@/store/slices/cartSlice';

export const useCart = () => {
  const dispatch = useAppDispatch();
  const cart = useAppSelector((state) => state[cartSliceName]);

  return {
    items: cart.items,
    totalItems: cart.totalItems,
    totalPrice: cart.totalPrice,
    addItem: (item: Parameters<typeof addItem>[0]) => dispatch(addItem(item)),
    removeItem: (id: string, size: string, variantId?: string, offerId?: string, offerSelectionId?: string, offerSlotId?: string) => dispatch(removeItem({ id, size, variantId, offerId, offerSelectionId, offerSlotId })),
    updateQuantity: (id: string, size: string, quantity: number, variantId?: string, offerId?: string, offerSelectionId?: string, offerSlotId?: string) => dispatch(updateQuantity({ id, size, quantity, variantId, offerId, offerSelectionId, offerSlotId })),
    clearCart: () => dispatch(clearCart()),
  };
};
