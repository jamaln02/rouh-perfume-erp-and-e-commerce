import { createSlice, PayloadAction } from "@reduxjs/toolkit";

export const adminOrdersSliceName = "adminOrders";

export interface AdminOrderItem {
  id: string;
  product_name: string;
  quantity: number;
  size: string | null;
  price: number;
  oil_mix?: string | null;
  bottle_size_ml?: string | null;
  packaging_items?: string | null;
}

export interface AdminOrder {
  id: string;
  customer_id?: string | null;
  customer_name: string;
  customer_phone: string;
  customer_address: string;
  city: string;
  status: string;
  total: number;
  shipping_cost: number;
  payment_method: string;
  notes: string | null;
  created_at: string;
  source?: string;
  order_status?: string;
  consumption_status?: string;
  prepared_at?: string | null;
  order_status_display?: string;
}

export interface AdminOrdersState {
  orders: AdminOrder[];
  loading: boolean;
  selected: AdminOrder | null;
  items: AdminOrderItem[];
  itemsLoading: boolean;
  filter: string;
  search: string;
  showEditDialog: boolean;
  editingOrder: AdminOrder | null;
  editItems: any[];
  deletingOrder: boolean;
}

const initialState: AdminOrdersState = {
  orders: [],
  loading: true,
  selected: null,
  items: [],
  itemsLoading: false,
  filter: "all",
  search: "",
  showEditDialog: false,
  editingOrder: null,
  editItems: [],
  deletingOrder: false,
};

const adminOrdersSlice = createSlice({
  name: adminOrdersSliceName,
  initialState,
  reducers: {
    setOrders(state, action: PayloadAction<AdminOrder[]>) {
      state.orders = action.payload;
      state.loading = false;
    },
    setLoading(state, action: PayloadAction<boolean>) {
      state.loading = action.payload;
    },
    setSelected(state, action: PayloadAction<AdminOrder | null>) {
      state.selected = action.payload;
    },
    setItems(state, action: PayloadAction<AdminOrderItem[]>) {
      state.items = action.payload;
    },
    setItemsLoading(state, action: PayloadAction<boolean>) {
      state.itemsLoading = action.payload;
    },
    setFilter(state, action: PayloadAction<string>) {
      state.filter = action.payload;
    },
    setSearch(state, action: PayloadAction<string>) {
      state.search = action.payload;
    },
    setShowEditDialog(state, action: PayloadAction<boolean>) {
      state.showEditDialog = action.payload;
    },
    setEditingOrder(state, action: PayloadAction<AdminOrder | null>) {
      state.editingOrder = action.payload;
    },
    setEditItems(state, action: PayloadAction<any[]>) {
      state.editItems = action.payload;
    },
    setDeletingOrder(state, action: PayloadAction<boolean>) {
      state.deletingOrder = action.payload;
    },
    resetOrdersState(state) {
      state.selected = null;
      state.items = [];
      state.showEditDialog = false;
      state.editingOrder = null;
      state.editItems = [];
      state.deletingOrder = false;
    },
  },
});

export const {
  setOrders,
  setLoading,
  setSelected,
  setItems,
  setItemsLoading,
  setFilter,
  setSearch,
  setShowEditDialog,
  setEditingOrder,
  setEditItems,
  setDeletingOrder,
  resetOrdersState,
} = adminOrdersSlice.actions;

export default adminOrdersSlice.reducer;
