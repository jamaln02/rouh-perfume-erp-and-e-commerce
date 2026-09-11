import { useCallback, useEffect, useRef } from "react";
import { useAppDispatch, useAppSelector } from "@/store";
import { wishlistSliceName, setIds, toggle as reduxToggle, remove, clear as reduxClear } from "@/store/slices/wishlistSlice";

const WISHLIST_STORAGE_KEY = "rouh-wishlist-v2";

export const useWishlist = () => {
  const dispatch = useAppDispatch();
  const wishlist = useAppSelector((state) => state[wishlistSliceName]);
  const idsRef = useRef(wishlist.ids);

  useEffect(() => {
    idsRef.current = wishlist.ids;
  }, [wishlist.ids]);

  const hydratedRef = useRef(false);

  useEffect(() => {
    try {
      const raw = window.localStorage.getItem(WISHLIST_STORAGE_KEY);
      if (raw) {
        const ids = JSON.parse(raw);
        if (Array.isArray(ids)) dispatch(setIds(ids.map(String).filter(Boolean)));
      }
    } catch {
      // localStorage may be unavailable; Redux remains usable for this session.
    } finally {
      hydratedRef.current = true;
    }
  }, [dispatch]);

  useEffect(() => {
    if (!hydratedRef.current) return;
    try {
      window.localStorage.setItem(WISHLIST_STORAGE_KEY, JSON.stringify(wishlist.ids));
    } catch {
      // Ignore storage quota/privacy errors.
    }
  }, [wishlist.ids]);

  const toggle = useCallback((id: string) => dispatch(reduxToggle(id)), [dispatch]);
  const removeItem = useCallback((id: string) => dispatch(remove(id)), [dispatch]);
  const clear = useCallback(() => dispatch(reduxClear()), [dispatch]);

  return {
    ids: wishlist.ids,
    count: wishlist.count,
    has: (id: string) => idsRef.current.includes(id),
    toggle,
    remove: removeItem,
    clear,
  };
};
