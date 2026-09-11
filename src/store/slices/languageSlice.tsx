import { createSlice, PayloadAction } from "@reduxjs/toolkit";

export const languageSliceName = "language";

type Lang = "ar" | "en";

interface LanguageState {
  lang: Lang;
  dir: "rtl" | "ltr";
}

const initialState: LanguageState = {
  lang: "ar",
  dir: "rtl",
};

const languageSlice = createSlice({
  name: languageSliceName,
  initialState,
  reducers: {
    setLang(state, action: PayloadAction<Lang>) {
      state.lang = action.payload;
      state.dir = action.payload === "ar" ? "rtl" : "ltr";
    },
  },
});

export const { setLang } = languageSlice.actions;
export default languageSlice.reducer;
