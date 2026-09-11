export const useRecentlyViewed = () => {
  // For now, use localStorage directly until we add a recentlyViewed slice
  const getRecentlyViewed = () => {
    try {
      const stored = localStorage.getItem("rouh_recently_viewed");
      if (stored) {
        const items = JSON.parse(stored);
        return items.map((item: { id: string }) => item.id);
      }
    } catch (error) {
    }
    return [];
  };

  const addToRecentlyViewed = (id: string) => {
    try {
      const stored = localStorage.getItem("rouh_recently_viewed");
      const items = stored ? JSON.parse(stored) : [];
      const filtered = items.filter((item: { id: string }) => item.id !== id);
      const updated = [{ id, timestamp: Date.now() }, ...filtered].slice(0, 10);
      localStorage.setItem("rouh_recently_viewed", JSON.stringify(updated));
    } catch (error) {
    }
  };

  const clearRecentlyViewed = () => {
    try {
      localStorage.removeItem("rouh_recently_viewed");
    } catch (error) {
    }
  };

  return {
    ids: getRecentlyViewed(),
    add: addToRecentlyViewed,
    clear: clearRecentlyViewed,
  };
};