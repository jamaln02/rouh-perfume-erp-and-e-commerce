import { createSlice, PayloadAction } from "@reduxjs/toolkit";

export const comparisonSliceName = "comparison";

interface ComparisonState {
  ids: string[];
  count: number;
}

const initialState: ComparisonState = {
  ids: [],
  count: 0,
};

const comparisonSlice = createSlice({
  name: comparisonSliceName,
  initialState,
  reducers: {
    setIds(state, action: PayloadAction<string[]>) {
      state.ids = action.payload;
      state.count = action.payload.length;
    },
    add(state, action: PayloadAction<string>) {
      if (state.ids.length >= 4) return; // Max 4 items
      if (!state.ids.includes(action.payload)) {
        state.ids.push(action.payload);
        state.count = state.ids.length;
      }
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

export const { setIds, add, remove, clear } = comparisonSlice.actions;
export default comparisonSlice.reducer;
