import { configureStore } from "@reduxjs/toolkit";
import { useDispatch, useSelector, TypedUseSelectorHook } from "react-redux";
import adminOrdersReducer, { adminOrdersSliceName } from "./slices/adminOrdersSlice";
import cartReducer, { cartSliceName } from "./slices/cartSlice";
import type { CartItem } from "./slices/cartSlice";
import wishlistReducer, { wishlistSliceName } from "./slices/wishlistSlice";
import comparisonReducer, { comparisonSliceName } from "./slices/comparisonSlice";
import languageReducer, { languageSliceName } from "./slices/languageSlice";

const CART_STORAGE_KEY = "rouh-cart-v2";

const loadPersistedCart = () => {
  if (typeof window === "undefined") return undefined;

  try {
    const raw = window.localStorage.getItem(CART_STORAGE_KEY);
    if (!raw) return undefined;
    const items = JSON.parse(raw) as CartItem[];
    if (!Array.isArray(items)) return undefined;

    const safeItems = items.filter((item) =>
      item &&
      typeof item.id === "string" &&
      typeof item.size === "string" &&
      typeof item.quantity === "number" &&
      item.quantity > 0 &&
      typeof item.price === "number"
    );

    return {
      [cartSliceName]: {
        items: safeItems,
        totalItems: safeItems.reduce((sum, item) => sum + item.quantity, 0),
        totalPrice: safeItems.reduce((sum, item) => sum + item.price * item.quantity, 0),
      },
    };
  } catch {
    return undefined;
  }
};

export const store = configureStore({
  reducer: {
    [adminOrdersSliceName]: adminOrdersReducer,
    [cartSliceName]: cartReducer,
    [wishlistSliceName]: wishlistReducer,
    [comparisonSliceName]: comparisonReducer,
    [languageSliceName]: languageReducer,
  },
  preloadedState: loadPersistedCart(),
  middleware: (getDefaultMiddleware) =>
    getDefaultMiddleware({
      serializableCheck: false,
    }),
});

let previousCartSignature = JSON.stringify(store.getState()[cartSliceName]);

if (typeof window !== "undefined") {
  store.subscribe(() => {
    try {
      const cart = store.getState()[cartSliceName];
      const signature = JSON.stringify(cart);
      if (signature === previousCartSignature) return;

      previousCartSignature = signature;
      window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart.items));
    } catch {
      // localStorage may be unavailable (privacy mode/quota). The in-memory cart still works.
    }
  });
}

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;

export const useAppDispatch = () => useDispatch<AppDispatch>();
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector;
