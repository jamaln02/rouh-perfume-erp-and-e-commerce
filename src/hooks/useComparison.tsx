import { useAppDispatch, useAppSelector } from '@/store';
import { comparisonSliceName, add, remove, clear } from '@/store/slices/comparisonSlice';

export const useComparison = () => {
  const dispatch = useAppDispatch();
  const comparison = useAppSelector((state) => state[comparisonSliceName]);

  return {
    ids: comparison.ids,
    count: comparison.count,
    has: (id: string) => comparison.ids.includes(id),
    add: (id: string) => dispatch(add(id)),
    remove: (id: string) => dispatch(remove(id)),
    clear: () => dispatch(clear()),
  };
};
