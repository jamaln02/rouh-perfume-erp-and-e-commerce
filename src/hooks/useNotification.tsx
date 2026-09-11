import { useState, useCallback } from 'react';
import { toast } from 'sonner';

export type NotificationType = 'success' | 'error' | 'warning' | 'info';

interface NotificationOptions {
  duration?: number;
  action?: {
    label: string;
    onClick: () => void;
  };
}

export const useNotification = () => {
  const [notifications, setNotifications] = useState<any[]>([]);

  const notify = useCallback(
    (
      message: string,
      type: NotificationType = 'info',
      options?: NotificationOptions
    ) => {
      const id = Date.now().toString();

      const notificationItem = {
        id,
        message,
        type,
        ...options,
      };

      setNotifications((prev) => [...prev, notificationItem]);

      // Show toast notification
      switch (type) {
        case 'success':
          toast.success(message, options);
          break;
        case 'error':
          toast.error(message, options);
          break;
        case 'warning':
          toast.warning(message, options);
          break;
        case 'info':
        default:
          toast.info(message, options);
          break;
      }

      // Auto-remove after duration
      if (options?.duration !== 0) {
        const timeout = setTimeout(() => {
          setNotifications((prev) => prev.filter((n) => n.id !== id));
        }, options?.duration || 4000);

        return () => clearTimeout(timeout);
      }
    },
    []
  );

  const removeNotification = useCallback((id: string) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
  }, []);

  const clearAll = useCallback(() => {
    setNotifications([]);
  }, []);

  return {
    notifications,
    notify,
    success: (msg: string, opts?: NotificationOptions) => notify(msg, 'success', opts),
    error: (msg: string, opts?: NotificationOptions) => notify(msg, 'error', opts),
    warning: (msg: string, opts?: NotificationOptions) => notify(msg, 'warning', opts),
    info: (msg: string, opts?: NotificationOptions) => notify(msg, 'info', opts),
    remove: removeNotification,
    clear: clearAll,
  };
};
