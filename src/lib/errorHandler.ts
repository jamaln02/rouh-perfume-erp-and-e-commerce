import { toast } from 'sonner';

export type ErrorSeverity = 'info' | 'warning' | 'error' | 'critical';

interface ErrorContext {
  endpoint?: string;
  method?: string;
  status?: number;
  responseData?: any;
}

/**
 * Centralized error handling system for API calls and internal errors
 */
export class ErrorHandler {
  private static readonly LOG_ERRORS = true;

  /**
   * Handle API errors with appropriate logging and user notifications
   */
  static async handleApiError(
    error: any,
    context: ErrorContext = {},
    showToast: boolean = true
  ): Promise<void> {
    const { endpoint, method = 'GET', status, responseData } = context;

    // Determine error message and severity
    let message = '';
    let severity: ErrorSeverity = 'error';

    if (status === 401) {
      message = 'جلسة انتهت. يرجى تسجيل الدخول مجدداً';
      severity = 'warning';
      // Clear auth token
      localStorage.removeItem('rouh_auth_token');
      sessionStorage.removeItem('rouh_auth_token');
    } else if (status === 403) {
      message = 'ليس لديك صلاحيات كافية لهذه العملية';
      severity = 'warning';
    } else if (status === 404) {
      message = 'العنصر المطلوب غير موجود';
      severity = 'info';
    } else if (status === 500) {
      message = 'حدث خطأ في الخادم. يرجى المحاولة لاحقاً';
      severity = 'critical';
    } else if (status === 422 || status === 400) {
      // Validation errors
      if (responseData?.errors) {
        const errors = Object.values(responseData.errors).flat() as string[];
        message = errors.join(', ');
      } else {
        message = responseData?.message || 'بيانات غير صحيحة';
      }
      severity = 'warning';
    } else if (error instanceof TypeError) {
      message = 'خطأ في الاتصال. تحقق من اتصالك بالإنترنت';
      severity = 'warning';
    } else {
      message = error?.message || 'حدث خطأ غير متوقع';
    }

    // Log error for debugging
    if (this.LOG_ERRORS) {
      console.error(`[${method}] ${endpoint}:`, {
        status,
        severity,
        message,
        responseData,
        originalError: error,
      });
    }

    // Show toast notification
    if (showToast) {
      switch (severity) {
        case 'critical':
        case 'error':
          toast.error(message);
          break;
        case 'warning':
          toast.warning(message);
          break;
        case 'info':
          toast.info(message);
          break;
      }
    }

    // Re-throw for caller to handle if needed
    throw new Error(message);
  }

  /**
   * Handle fetch response and throw if not ok
   */
  static async validateResponse(
    response: Response,
    endpoint?: string,
    showErrorToast: boolean = true
  ): Promise<any> {
    const contentType = response.headers.get('content-type');
    let responseData: any;

    try {
      if (contentType?.includes('application/json')) {
        responseData = await response.json();
      } else {
        responseData = await response.text();
      }
    } catch {
      responseData = null;
    }

    if (!response.ok) {
      await this.handleApiError(
        new Error(`HTTP ${response.status}`),
        {
          endpoint,
          status: response.status,
          responseData,
          method: 'fetch',
        },
        showErrorToast
      );
    }

    return responseData;
  }

  /**
   * Handle form validation errors
   */
  static handleValidationError(
    errors: Record<string, string | string[]>
  ): { field: string; message: string }[] {
    return Object.entries(errors).map(([field, message]) => ({
      field,
      message: Array.isArray(message) ? message[0] : message,
    }));
  }

  /**
   * Safe API call wrapper
   */
  static async safeApiCall<T>(
    url: string,
    options: RequestInit = {},
    showErrorToast: boolean = true
  ): Promise<T | null> {
    try {
      const response = await fetch(url, options);
      return await this.validateResponse(response, url, showErrorToast);
    } catch (error) {
      console.error('API call failed:', error);
      return null;
    }
  }

  /**
   * Check if error indicates authentication failure
   */
  static isAuthError(status?: number): boolean {
    return status === 401 || status === 403;
  }

  /**
   * Check if error is network-related
   */
  static isNetworkError(error: any): boolean {
    return (
      error instanceof TypeError ||
      error?.message?.includes('Network') ||
      error?.message?.includes('fetch')
    );
  }
}

/**
 * Utility to safely extract and log errors without alerting user
 */
export function logError(error: any, context?: string): void {
  if (console && typeof console.error === 'function') {
    console.error(`${context ? `[${context}] ` : ''}`, error);
  }
}

/**
 * Utility to silently handle errors (no console.error, no toast)
 */
export function silenceError(promise: Promise<any>): Promise<any> {
  return promise.catch(() => {
    // intentionally silent
  });
}
