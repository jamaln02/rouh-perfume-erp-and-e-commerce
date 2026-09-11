import { createSlice, PayloadAction } from "@reduxjs/toolkit";

export const wishlistSliceName = "wishlist";

interface WishlistState {
  ids: string[];
  count: number;
}

const initialState: WishlistState = {
  ids: [],
  count: 0,
};

const wishlistSlice = createSlice({
  name: wishlistSliceName,
  initialState,
  reducers: {
    setIds(state, action: PayloadAction<string[]>) {
      state.ids = action.payload;
      state.count = action.payload.length;
    },
    toggle(state, action: PayloadAction<string>) {
      if (state.ids.includes(action.payload)) {
        state.ids = state.ids.filter((id) => id !== action.payload);
      } else {
        state.ids = [action.payload, ...state.ids];
      }
      state.count = state.ids.length;
    },
    remove(state, action: PayloadAction<string>) {
      state.ids = state.ids.filter((id) => id !== action.payload);
      state.count = state.ids.length;
    },
    clear(state) {
      state.ids = [];
      state.count = 0;
    },
  },
});

export const { setIds, toggle, remove, clear } = wishlistSlice.actions;
export default wishlistSlice.reducer;
