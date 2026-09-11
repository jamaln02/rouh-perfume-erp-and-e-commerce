import { createSlice, PayloadAction } from "@reduxjs/toolkit";

export const cartSliceName = "cart";

export interface CartItem {
  id: string;
  name: string;
  nameAr: string;
  price: number;
  image: string;
  quantity: number;
  size: string;
  variantId?: string;
  isBundle?: boolean;
  originalPrice?: number;
  offerId?: string;
  offerName?: string;
  offerSelectionId?: string;
  offerSlotId?: string;
}

interface CartState {
  items: CartItem[];
  totalItems: number;
  totalPrice: number;
}

const initialState: CartState = {
  items: [],
  totalItems: 0,
  totalPrice: 0,
};

const cartSlice = createSlice({
  name: cartSliceName,
  initialState,
  reducers: {
    setItems(state, action: PayloadAction<CartItem[]>) {
      state.items = action.payload;
      state.totalItems = action.payload.reduce((sum, item) => sum + item.quantity, 0);
      state.totalPrice = action.payload.reduce((sum, item) => sum + item.price * item.quantity, 0);
    },
    addItem(state, action: PayloadAction<Omit<CartItem, "quantity">>) {
      const existing = state.items.find(
        (item) => item.id === action.payload.id && item.size === action.payload.size && item.variantId === action.payload.variantId && item.offerId === action.payload.offerId && item.offerSelectionId === action.payload.offerSelectionId && item.offerSlotId === action.payload.offerSlotId
      );
      if (existing) {
        existing.quantity += 1;
      } else {
        state.items.push({ ...action.payload, quantity: 1 });
      }
      state.totalItems = state.items.reduce((sum, item) => sum + item.quantity, 0);
      state.totalPrice = state.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    },
    removeItem(state, action: PayloadAction<{ id: string; size: string; variantId?: string; offerId?: string; offerSelectionId?: string; offerSlotId?: string }>) {
      state.items = state.items.filter(
        (item) => !(item.id === action.payload.id && item.size === action.payload.size && item.variantId === action.payload.variantId && item.offerId === action.payload.offerId && item.offerSelectionId === action.payload.offerSelectionId && item.offerSlotId === action.payload.offerSlotId)
      );
      state.totalItems = state.items.reduce((sum, item) => sum + item.quantity, 0);
      state.totalPrice = state.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    },
    updateQuantity(state, action: PayloadAction<{ id: string; size: string; quantity: number; variantId?: string; offerId?: string; offerSelectionId?: string; offerSlotId?: string }>) {
      if (action.payload.quantity < 1) return;
      const item = state.items.find(
        (item) => item.id === action.payload.id && item.size === action.payload.size && item.variantId === action.payload.variantId && item.offerId === action.payload.offerId && item.offerSelectionId === action.payload.offerSelectionId && item.offerSlotId === action.payload.offerSlotId
      );
      if (item) {
        item.quantity = action.payload.quantity;
      }
      state.totalItems = state.items.reduce((sum, item) => sum + item.quantity, 0);
      state.totalPrice = state.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    },
    clearCart(state) {
      state.items = [];
      state.totalItems = 0;
      state.totalPrice = 0;
    },
  },
});

export const { setItems, addItem, removeItem, updateQuantity, clearCart } = cartSlice.actions;
export default cartSlice.reducer;
